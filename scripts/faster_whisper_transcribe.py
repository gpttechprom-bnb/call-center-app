#!/usr/bin/env python3
import argparse
import contextlib
import json
import os
import sys
import traceback
from collections import defaultdict
from pathlib import Path
from typing import Any

PROGRESS_PREFIX = "__WHISPER_PROGRESS__"


def emit(payload: dict, exit_code: int) -> None:
    sys.stdout.write(json.dumps(payload, ensure_ascii=False))
    sys.exit(exit_code)


def fail(message: str, exit_code: int = 1, details: str | None = None) -> None:
    payload = {"ok": False, "error": message}
    if details:
        payload["details"] = details
    emit(payload, exit_code)


def emit_progress(payload: dict[str, Any]) -> None:
    sys.stderr.write(f"{PROGRESS_PREFIX}{json.dumps(payload, ensure_ascii=False)}\n")
    sys.stderr.flush()


def format_timestamp(seconds: float) -> str:
    total_milliseconds = int(round(seconds * 1000))
    hours, remainder = divmod(total_milliseconds, 3_600_000)
    minutes, remainder = divmod(remainder, 60_000)
    secs, milliseconds = divmod(remainder, 1000)

    if hours > 0:
        return f"{hours:02d}:{minutes:02d}:{secs:02d}.{milliseconds:03d}"

    return f"{minutes:02d}:{secs:02d}.{milliseconds:03d}"


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description="Transcribe audio with faster-whisper")
    parser.add_argument("--audio-path", required=True)
    parser.add_argument("--language", default="auto")
    parser.add_argument("--model", default="large-v3")
    parser.add_argument("--device", default="cpu")
    parser.add_argument("--compute-type", default="int8")
    parser.add_argument("--beam-size", type=int, default=5)
    parser.add_argument("--initial-prompt", default="")
    parser.add_argument("--vad-filter", action="store_true")
    parser.add_argument("--word-timestamps", action="store_true")
    parser.add_argument("--diarization-enabled", action="store_true")
    parser.add_argument(
        "--diarization-model",
        default="pyannote/speaker-diarization-community-1",
    )
    parser.add_argument("--diarization-num-speakers", type=int, default=2)
    parser.add_argument("--turn-merge-gap", type=float, default=0.8)
    return parser


def to_float(value: Any, default: float = 0.0) -> float:
    try:
        if value is None:
            return default
        return float(value)
    except (TypeError, ValueError):
        return default


def normalize_word(word_text: str) -> str:
    return word_text if word_text else ""


def load_audio_waveform(audio_path: Path) -> tuple[Any, int]:
    try:
        import av
        import numpy as np
        import torch
    except Exception as exc:
        fail(
            "Для визначення автора реплік потрібні пакети av, numpy і torch. Перевстановіть Python-залежності faster-whisper/pyannote.",
            3,
            str(exc),
        )

    try:
        container = av.open(str(audio_path))
    except Exception as exc:
        fail("Не вдалося відкрити аудіофайл для diarization.", 4, str(exc))

    audio_stream = next((stream for stream in container.streams if stream.type == "audio"), None)
    if audio_stream is None:
        fail("В аудіофайлі не знайдено звукову доріжку.", 4)

    resampler = av.audio.resampler.AudioResampler(format="fltp", layout="mono", rate=16_000)
    chunks: list[Any] = []

    for frame in container.decode(audio_stream):
        resampled = resampler.resample(frame)
        frames = resampled if isinstance(resampled, list) else [resampled]

        for resampled_frame in frames:
            array = resampled_frame.to_ndarray()
            if array.ndim == 2:
                chunks.append(array[0])
            else:
                chunks.append(array)

    container.close()

    if not chunks:
        fail("Не вдалося декодувати аудіо для speaker diarization.", 4)

    waveform = np.concatenate(chunks).astype("float32")
    return torch.from_numpy(waveform).unsqueeze(0), 16_000


def normalize_annotation(annotation: Any) -> list[dict[str, Any]]:
    turns: list[dict[str, Any]] = []

    if hasattr(annotation, "itertracks"):
        iterator = annotation.itertracks(yield_label=True)
        for turn, _, speaker in iterator:
            start = round(to_float(getattr(turn, "start", 0.0)), 3)
            end = round(to_float(getattr(turn, "end", start)), 3)
            if end <= start:
                continue

            speaker_id = str(speaker)
            turns.append(
                {
                    "start": start,
                    "end": end,
                    "start_label": format_timestamp(start),
                    "end_label": format_timestamp(end),
                    "speaker_id": speaker_id,
                }
            )

    return sorted(turns, key=lambda item: (item["start"], item["end"], item["speaker_id"]))


def assign_speaker(start: float, end: float, diarization_turns: list[dict[str, Any]]) -> str | None:
    if not diarization_turns:
        return None

    overlap_scores: dict[str, float] = defaultdict(float)
    segment_midpoint = (start + end) / 2 if end > start else start
    nearest_speaker: str | None = None
    nearest_distance: float | None = None

    for turn in diarization_turns:
        overlap_start = max(start, turn["start"])
        overlap_end = min(end, turn["end"])
        overlap_duration = max(0.0, overlap_end - overlap_start)

        if overlap_duration > 0:
            overlap_scores[turn["speaker_id"]] += overlap_duration

        if turn["start"] <= segment_midpoint <= turn["end"]:
            return str(turn["speaker_id"])

        distance = min(abs(segment_midpoint - turn["start"]), abs(segment_midpoint - turn["end"]))
        if nearest_distance is None or distance < nearest_distance:
            nearest_distance = distance
            nearest_speaker = str(turn["speaker_id"])

    if overlap_scores:
        return max(overlap_scores.items(), key=lambda item: item[1])[0]

    return nearest_speaker


def extract_segment_words(segment: Any) -> list[dict[str, Any]]:
    segment_start = round(to_float(getattr(segment, "start", 0.0)), 3)
    segment_end = round(to_float(getattr(segment, "end", segment_start)), 3)
    raw_words = getattr(segment, "words", None) or []
    words: list[dict[str, Any]] = []

    for word in raw_words:
        word_text = normalize_word((getattr(word, "word", None) or "").replace("\n", " "))
        if word_text == "":
            continue

        start = round(to_float(getattr(word, "start", None), segment_start), 3)
        end = round(to_float(getattr(word, "end", None), start), 3)
        if end <= start:
            end = max(start + 0.01, segment_end)

        words.append(
            {
                "start": start,
                "end": end,
                "start_label": format_timestamp(start),
                "end_label": format_timestamp(end),
                "text": word_text,
                "speaker_id": None,
            }
        )

    if words:
        return words

    text = ((getattr(segment, "text", None) or "").strip()).replace("\n", " ")
    if text == "":
        return []

    return [
        {
            "start": segment_start,
            "end": segment_end,
            "start_label": format_timestamp(segment_start),
            "end_label": format_timestamp(segment_end),
            "text": text,
            "speaker_id": None,
        }
    ]


def dominant_speaker(words: list[dict[str, Any]]) -> str | None:
    durations: dict[str, float] = defaultdict(float)

    for word in words:
        speaker_id = word.get("speaker_id")
        if not speaker_id:
            continue

        duration = max(0.01, to_float(word.get("end")) - to_float(word.get("start")))
        durations[str(speaker_id)] += duration

    if not durations:
        return None

    return max(durations.items(), key=lambda item: item[1])[0]


def fill_missing_word_speakers(words: list[dict[str, Any]]) -> None:
    for index, word in enumerate(words):
        if word.get("speaker_id"):
            continue

        previous_speaker = next(
            (item.get("speaker_id") for item in reversed(words[:index]) if item.get("speaker_id")),
            None,
        )
        next_speaker = next(
            (item.get("speaker_id") for item in words[index + 1 :] if item.get("speaker_id")),
            None,
        )

        if previous_speaker and previous_speaker == next_speaker:
            word["speaker_id"] = previous_speaker
            continue

        if previous_speaker and next_speaker is None:
            word["speaker_id"] = previous_speaker
            continue

        if next_speaker and previous_speaker is None:
            word["speaker_id"] = next_speaker


def build_speaker_turns(words: list[dict[str, Any]], merge_gap_seconds: float) -> list[dict[str, Any]]:
    if not words:
        return []

    ordered_words = sorted(words, key=lambda item: (to_float(item["start"]), to_float(item["end"])))
    turns: list[dict[str, Any]] = []
    current_turn: dict[str, Any] | None = None

    for word in ordered_words:
        word_text = str(word.get("text", ""))
        if word_text.strip() == "":
            continue

        speaker_id = word.get("speaker_id")
        word_start = round(to_float(word.get("start")), 3)
        word_end = round(to_float(word.get("end"), word_start), 3)

        should_split = current_turn is None
        if current_turn is not None:
            gap = word_start - to_float(current_turn["end"], word_start)
            should_split = (
                current_turn.get("speaker_id") != speaker_id
                or gap > merge_gap_seconds
            )

        if should_split:
            if current_turn is not None:
                current_turn["text"] = "".join(current_turn["parts"]).strip()
                turns.append(current_turn)

            current_turn = {
                "start": word_start,
                "end": word_end,
                "speaker_id": speaker_id,
                "parts": [word_text],
            }
            continue

        current_turn["end"] = word_end
        current_turn["parts"].append(word_text)

    if current_turn is not None:
        current_turn["text"] = "".join(current_turn["parts"]).strip()
        turns.append(current_turn)

    normalized_turns: list[dict[str, Any]] = []
    for turn in turns:
        text = str(turn.get("text", "")).strip()
        if text == "":
            continue

        start = round(to_float(turn["start"]), 3)
        end = round(to_float(turn["end"], start), 3)
        normalized_turns.append(
            {
                "start": start,
                "end": end,
                "start_label": format_timestamp(start),
                "end_label": format_timestamp(end),
                "speaker_id": turn.get("speaker_id"),
                "text": text,
            }
        )

    return normalized_turns


def run_diarization(audio_path: Path, args: argparse.Namespace) -> tuple[list[dict[str, Any]], dict[str, Any]]:
    token = os.environ.get("PYANNOTE_AUTH_TOKEN", "").strip()
    if not token:
        fail(
            "Для визначення автора реплік потрібен Hugging Face token з доступом до pyannote/speaker-diarization-community-1.",
            4,
        )

    try:
        import torch
        from pyannote.audio import Pipeline
    except Exception:
        fail(
            "Python package 'pyannote.audio' is not installed. Install it with: pip install -r scripts/requirements-faster-whisper.txt",
            3,
        )

    waveform, sample_rate = load_audio_waveform(audio_path)
    try:
        with contextlib.redirect_stdout(sys.stderr):
            pipeline = Pipeline.from_pretrained(args.diarization_model, token=token)
    except Exception as exc:
        message = str(exc).lower()
        if "gated repo" in message or "access to model" in message or "403" in message:
            fail(
                "Hugging Face token не має доступу до pyannote/speaker-diarization-community-1. "
                "Відкрийте сторінку моделі, прийміть умови доступу і збережіть токен повторно в налаштуваннях.",
                4,
                traceback.format_exc(limit=6),
            )
        raise

    if args.device and args.device != "cpu":
        try:
            pipeline.to(torch.device(args.device))
            waveform = waveform.to(torch.device(args.device))
        except Exception:
            # If the diarization pipeline cannot move to the requested device,
            # keep processing on CPU instead of failing the whole request.
            waveform = waveform.cpu()

    diarization_kwargs: dict[str, Any] = {}
    if args.diarization_num_speakers and args.diarization_num_speakers > 0:
        diarization_kwargs["num_speakers"] = args.diarization_num_speakers

    with contextlib.redirect_stdout(sys.stderr):
        output = pipeline({"waveform": waveform, "sample_rate": sample_rate}, **diarization_kwargs)
    annotation = (
        getattr(output, "exclusive_speaker_diarization", None)
        or getattr(output, "speaker_diarization", None)
        or output
    )
    diarization_turns = normalize_annotation(annotation)
    speakers = sorted({turn["speaker_id"] for turn in diarization_turns if turn.get("speaker_id")})

    return diarization_turns, {
        "enabled": True,
        "applied": bool(diarization_turns),
        "model": args.diarization_model,
        "num_speakers": args.diarization_num_speakers,
        "speakers": speakers,
    }


def main() -> None:
    parser = build_parser()
    args = parser.parse_args()

    audio_path = Path(args.audio_path).expanduser().resolve()
    if not audio_path.exists():
        fail("Аудіофайл не знайдено.", 2)

    try:
        from faster_whisper import WhisperModel
    except Exception:
        fail(
            "Python package 'faster-whisper' is not installed. Install it with: pip install -r scripts/requirements-faster-whisper.txt",
            3,
        )

    try:
        model = WhisperModel(
            args.model,
            device=args.device,
            compute_type=args.compute_type,
        )

        transcribe_kwargs = {
            "beam_size": args.beam_size,
            "vad_filter": bool(args.vad_filter),
            "word_timestamps": bool(args.word_timestamps),
        }
        if args.language and args.language != "auto":
            transcribe_kwargs["language"] = args.language

        if args.initial_prompt:
            transcribe_kwargs["initial_prompt"] = args.initial_prompt

        segments_generator, info = model.transcribe(str(audio_path), **transcribe_kwargs)

        diarization_turns: list[dict[str, Any]] = []
        diarization_meta = {
            "enabled": bool(args.diarization_enabled),
            "applied": False,
            "model": None,
            "num_speakers": None,
            "speakers": [],
        }

        if args.diarization_enabled:
            diarization_turns, diarization_meta = run_diarization(audio_path, args)

        plain_lines: list[str] = []
        formatted_lines: list[str] = []
        collected_segments: list[dict[str, Any]] = []
        diarized_words: list[dict[str, Any]] = []

        emit_progress(
            {
                "status": "starting",
                "stage": "transcription",
                "segments_count": 0,
                "text": "",
                "formatted_text": "",
                "message": "Whisper запущено. Нові сегменти з'являтимуться тут у міру розпізнавання.",
            }
        )

        for segment in segments_generator:
            text = ((segment.text or "").strip()).replace("\n", " ")
            if not text:
                continue

            segment_start = round(to_float(getattr(segment, "start", 0.0)), 3)
            segment_end = round(to_float(getattr(segment, "end", 0.0)), 3)
            formatted_line = (
                f"[{format_timestamp(segment_start)} - {format_timestamp(segment_end)}] {text}"
            )

            plain_lines.append(text)
            formatted_lines.append(formatted_line)
            emit_progress(
                {
                    "status": "running",
                    "stage": "transcription",
                    "segments_count": len(formatted_lines),
                    "text": "\n".join(plain_lines).strip(),
                    "formatted_text": "\n".join(formatted_lines).strip(),
                    "latest_segment": {
                        "start": segment_start,
                        "end": segment_end,
                        "start_label": format_timestamp(segment_start),
                        "end_label": format_timestamp(segment_end),
                        "text": text,
                    },
                }
            )

            segment_words = extract_segment_words(segment)
            for word in segment_words:
                word["speaker_id"] = assign_speaker(
                    to_float(word["start"]),
                    to_float(word["end"]),
                    diarization_turns,
                )

            fill_missing_word_speakers(segment_words)
            segment_speaker = dominant_speaker(segment_words)

            collected_segments.append(
                {
                    "start": segment_start,
                    "end": segment_end,
                    "start_label": format_timestamp(segment_start),
                    "end_label": format_timestamp(segment_end),
                    "text": text,
                    "speaker_id": segment_speaker,
                    "words": segment_words,
                }
            )

            diarized_words.extend(segment_words)

        emit_progress(
            {
                "status": "postprocessing",
                "stage": "transcription",
                "segments_count": len(formatted_lines),
                "text": "\n".join(plain_lines).strip(),
                "formatted_text": "\n".join(formatted_lines).strip(),
                "message": "Whisper завершив розпізнавання сегментів. Готуємо фінальний текст для інтерфейсу.",
            }
        )

        speaker_turns = build_speaker_turns(diarized_words, args.turn_merge_gap) if diarization_turns else []

        emit(
            {
                "ok": True,
                "language": getattr(info, "language", args.language),
                "language_probability": getattr(info, "language_probability", None),
                "duration": getattr(info, "duration", None),
                "text": "\n".join(plain_lines).strip(),
                "formatted_text": "\n".join(formatted_lines).strip(),
                "segments": collected_segments,
                "speaker_turns": speaker_turns,
                "speaker_diarization": diarization_meta,
            },
            0,
        )
    except Exception as exc:
        fail(
            f"faster-whisper failed: {exc}",
            4,
            traceback.format_exc(limit=6),
        )


if __name__ == "__main__":
    main()
