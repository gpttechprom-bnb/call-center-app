<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400"></a></p>

<p align="center">
<a href="https://travis-ci.org/laravel/framework"><img src="https://travis-ci.org/laravel/framework.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Call Center Module

This repository now contains a Laravel-backed call center dashboard at `/call-center`.

- The UI keeps working even before migrations run: the page falls back to built-in demo data.
- After migrations and seeding, the dashboard reads calls and score items from MySQL.
- Demo records are centralized in `App\Support\CallCenterDemoData` and reused by both the controller fallback and the database seeder.

## First Server-Side Step After Pull

Run the Laravel database setup so the dashboard starts using persisted records instead of the fallback payload:

```bash
php artisan migrate --seed
```

If you want to reseed just the call center records later:

```bash
php artisan db:seed --class=CallCenterSeeder
```

## Transcription Backend

The `Транскрибація` section now has a real backend:

- `POST /api/call-center/transcriptions` accepts an uploaded audio file or a remote audio URL
- Laravel stores the source file under `storage/app/call-center/transcriptions/...`
- Python transcription is executed through `scripts/faster_whisper_transcribe.py`
- The transcript is returned as JSON together with a checklist-based QA score

### faster-whisper setup

Install the Python dependency set used by the transcription endpoint:

```bash
python3 -m pip install -r scripts/requirements-faster-whisper.txt
```

Optional environment variables:

```bash
CALL_CENTER_PYTHON_BINARY=python3
CALL_CENTER_TRANSCRIPTION_STORAGE_DIR=call-center/transcriptions
FASTER_WHISPER_CACHE_DIR=call-center/faster-whisper-cache
FASTER_WHISPER_MODEL=small
FASTER_WHISPER_DEVICE=cpu
FASTER_WHISPER_COMPUTE_TYPE=int8
FASTER_WHISPER_BEAM_SIZE=5
FASTER_WHISPER_VAD_FILTER=true
FASTER_WHISPER_WORD_TIMESTAMPS=true
FASTER_WHISPER_TIMEOUT=1800
CALL_CENTER_SPEAKER_DIARIZATION_ENABLED=false
CALL_CENTER_SPEAKER_DIARIZATION_MODEL=pyannote/speaker-diarization-community-1
CALL_CENTER_SPEAKER_DIARIZATION_NUM_SPEAKERS=2
CALL_CENTER_SPEAKER_DIARIZATION_MERGE_GAP_SECONDS=0.8
PYANNOTE_AUTH_TOKEN=
```

Notes:

- On the first real transcription run, `faster-whisper` may download the selected model into the cache directory.
- For accurate `Менеджер / Клієнт` labeling, enable speaker diarization and add a Hugging Face token with access to `pyannote/speaker-diarization-community-1`.
- The transcription script now requests word timestamps from `faster-whisper`, assigns each word to a diarized speaker, and only then builds dialog turns. This greatly reduces mixed/merged replies compared to the old text-only heuristic.
- If diarization is disabled, the UI still falls back to heuristic labels, but they are less reliable than the pyannote path.

## Server-Side Scheduler

The call-center automation is a server background process. Browser tabs only
save settings, toggle Play/Pause, and display state; they must not be the thing
that keeps nightly processing alive.

Install the Laravel scheduler cron on the production server:

```bash
sudo cp ops/cron/laravel-scheduler /etc/cron.d/llm-yaprofi-scheduler
sudo service cron reload
```

For a user crontab on the production server instead:

```bash
crontab ops/cron/laravel-scheduler.user
```

The cron entry runs this every minute inside the PHP container:

```bash
docker exec llm_yaprofi_php php /var/www/artisan schedule:run
```

Laravel then starts `call-center:alt-auto-worker --max-seconds=55` only inside
the configured automation window. If the server is online, the nightly queue
does not depend on whether any laptop or browser tab is open.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains over 1500 video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the Laravel [Patreon page](https://patreon.com/taylorotwell).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Cubet Techno Labs](https://cubettech.com)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[Many](https://www.many.co.uk)**
- **[Webdock, Fast VPS Hosting](https://www.webdock.io/en)**
- **[DevSquad](https://devsquad.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[OP.GG](https://op.gg)**
- **[WebReinvent](https://webreinvent.com/?utm_source=laravel&utm_medium=github&utm_campaign=patreon-sponsors)**
- **[Lendio](https://lendio.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
