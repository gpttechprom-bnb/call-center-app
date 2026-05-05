<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Список дзвінків</title>
    <style>
        :root {
            --sidebar-width: 108px;
            --page: #f0f2f5;
            --card: #ffffff;
            --header: #5b9ff2;
            --header-dark: #347fdf;
            --grid: #d3dff1;
            --text: #1d2436;
            --muted: #7c8398;
            --row: #fbfcff;
            --row-alt: #f6f9fe;
            --row-active: #ecf3ff;
            --good: #69bf80;
            --bad: #ea95a5;
            --call-direction-in: #63c38f;
            --call-direction-out: #5da9ea;
            --accent: #1877f2;
            --accent-dark: #0f66dc;
            --soft: #eef4ff;
            --score-high-bg: #e8f7ee;
            --score-high-fg: #199c59;
            --score-mid-bg: #eef9f2;
            --score-mid-fg: #27a764;
            --score-low-bg: #ffe8ec;
            --score-low-fg: #dd3f63;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            color: var(--text);
            background: var(--page);
        }

        .app-shell {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: var(--sidebar-width);
            flex: 0 0 auto;
            background: linear-gradient(180deg, #2b2d33 0%, #24262c 100%);
            color: #ffffff;
            box-shadow: 6px 0 18px rgba(17, 14, 29, 0.08);
        }

        .sidebar-inner {
            display: flex;
            flex-direction: column;
            height: 100vh;
            position: sticky;
            top: 0;
            padding: 12px 10px 16px;
        }

        .sidebar-top {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
            padding-bottom: 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            color: inherit;
            text-decoration: none;
        }

        .sidebar-brand-mark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 52px;
            height: 52px;
            border-radius: 18px;
            background: linear-gradient(180deg, #d3ebff 0%, #9ec8ff 100%);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.68);
        }

        .sidebar-brand-mark svg {
            display: block;
            width: 40px;
            height: 40px;
        }

        .sidebar-brand-copy {
            display: none;
        }

        .sidebar-brand-title {
            display: block;
            font-size: 15px;
            font-weight: 700;
            white-space: nowrap;
        }

        .sidebar-brand-subtitle {
            display: block;
            margin-top: 3px;
            color: rgba(255, 255, 255, 0.56);
            font-size: 12px;
            white-space: nowrap;
        }

        .sidebar-nav {
            display: grid;
            justify-items: center;
            gap: 12px;
            padding-top: 2px;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            width: 82px;
            border: 0;
            border-radius: 18px;
            padding: 10px 4px 10px;
            background: transparent;
            color: rgba(255, 255, 255, 0.86);
            text-align: center;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.16s ease;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .nav-item.active {
            background: linear-gradient(180deg, rgba(24, 119, 242, 0.28), rgba(24, 119, 242, 0.18));
            box-shadow: inset 3px 0 0 #65aaff;
        }

        .nav-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.08);
            color: #d6e5ff;
            flex: 0 0 auto;
        }

        .nav-item.active .nav-icon {
            background: linear-gradient(180deg, #59a8ff, #1877f2);
            color: #ffffff;
            box-shadow: 0 10px 18px rgba(24, 119, 242, 0.28);
        }

        .nav-icon svg {
            width: 17px;
            height: 17px;
            display: block;
            stroke: currentColor;
            fill: none;
            stroke-width: 2.1;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .nav-copy {
            width: 100%;
            text-align: center;
        }

        .nav-title {
            display: block;
            width: 100%;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.3;
            text-align: center;
            white-space: normal;
        }

        .nav-meta {
            display: none;
        }

        .sidebar-bottom {
            margin-top: auto;
            padding-top: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            display: flex;
            justify-content: center;
        }

        .logout-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: auto;
            color: #ffffff;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            line-height: 1;
            white-space: nowrap;
        }

        .logout-link svg {
            width: 14px;
            height: 14px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2.2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .main-area {
            flex: 1 1 auto;
            min-width: 0;
            padding: 22px 28px;
            background: var(--page);
        }

        .page {
            max-width: 1380px;
            margin: 0 auto;
            display: grid;
            gap: 24px;
        }

        .table-card {
            border: 1px solid #dde3f0;
            border-radius: 24px;
            overflow: hidden;
            background: var(--card);
            box-shadow: 0 24px 55px rgba(41, 33, 73, 0.08);
        }

        .calls-page,
        .managers-page {
            max-width: none;
            margin: -22px -28px;
            min-height: calc(100vh - 44px);
            gap: 0;
        }

        .calls-page .table-card,
        .managers-page .table-card {
            min-height: calc(100vh - 44px);
            border: 0;
            border-radius: 0;
            box-shadow: none;
        }

        .calls-page .card-head,
        .managers-page .card-head {
            padding: 20px 24px 14px;
        }

        .content-section {
            display: none;
        }

        .content-section.active {
            display: block;
        }

        .card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 14px 20px;
            border-bottom: 1px solid #e3e8f3;
            background: linear-gradient(180deg, #fbfdff, #f2f7ff);
        }

        .card-head-main {
            flex: 1 1 auto;
            min-width: 0;
        }

        .card-title-box {
            display: inline-flex;
            align-items: center;
            min-height: auto;
            padding: 0;
            border-radius: 0;
            background: transparent;
            box-shadow: none;
        }

        .card-kicker {
            margin-bottom: 8px;
            color: #8b91a3;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.16em;
        }

        .card-title {
            margin: 0;
            font-size: 30px;
            line-height: 1.12;
        }

        .card-text {
            margin: 10px 0 0;
            max-width: 760px;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.6;
        }

        .visually-hidden {
            position: absolute;
            width: 1px;
            height: 1px;
            margin: -1px;
            padding: 0;
            overflow: hidden;
            border: 0;
            clip: rect(0 0 0 0);
            white-space: nowrap;
        }

        .card-meta {
            text-align: right;
            white-space: nowrap;
        }

        .card-meta strong {
            display: block;
            font-size: 15px;
            margin-bottom: 6px;
        }

        .card-meta span {
            color: var(--muted);
            font-size: 14px;
        }

        .card-side {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex: 0 0 auto;
        }

        .filter-panel {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            padding: 0;
            border: 0;
            border-radius: 0;
            background: transparent;
        }

        .filter-field {
            display: flex;
            flex-direction: column;
            gap: 0;
            min-width: 200px;
        }

        .filter-select-field {
            position: relative;
        }

        .filter-select-field.open {
            z-index: 60;
        }

        .filter-search-field {
            position: relative;
            min-width: 220px;
        }

        .filter-search-field::before {
            content: "";
            position: absolute;
            left: 16px;
            top: 50%;
            width: 12px;
            height: 12px;
            border: 2px solid #b8c4dc;
            border-radius: 50%;
            transform: translateY(-60%);
            pointer-events: none;
        }

        .filter-search-field::after {
            content: "";
            position: absolute;
            left: 28px;
            top: 50%;
            width: 7px;
            height: 2px;
            background: #b8c4dc;
            border-radius: 999px;
            transform: translateY(4px) rotate(45deg);
            transform-origin: left center;
            pointer-events: none;
        }

        .filter-date-field {
            position: relative;
            min-width: 260px;
        }

        .filter-label {
            position: absolute;
            width: 1px;
            height: 1px;
            margin: -1px;
            padding: 0;
            overflow: hidden;
            border: 0;
            clip: rect(0 0 0 0);
            white-space: nowrap;
        }

        .filter-select-native {
            position: absolute;
            inset: 0;
            opacity: 0;
            pointer-events: none;
        }

        .filter-select-trigger {
            height: 44px;
            width: 100%;
            border: 1px solid #cbdaf1;
            border-radius: 14px;
            padding: 0 38px 0 14px;
            background: #ffffff;
            color: #24304e;
            font-size: 14px;
            font-weight: 500;
            line-height: 1;
            text-align: center;
            font-family: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            vertical-align: middle;
            padding-bottom: 1px;
        }

        .filter-select-field::after {
            content: "";
            position: absolute;
            right: 16px;
            top: 50%;
            width: 9px;
            height: 9px;
            border-right: 2px solid #8ea3ca;
            border-bottom: 2px solid #8ea3ca;
            transform: translateY(-65%) rotate(45deg);
            pointer-events: none;
            transition: border-color 0.2s ease, transform 0.2s ease;
        }

        .filter-select-field:focus-within::after,
        .filter-select-field.open::after {
            border-color: #5a7fc0;
            transform: translateY(-45%) rotate(45deg);
        }

        .filter-select-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            z-index: 40;
            padding: 6px 0;
            border: 1px solid #cbdaf1;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 18px 40px rgba(21, 42, 84, 0.18);
            backdrop-filter: blur(10px);
            max-height: 220px;
            overflow-y: auto;
            overflow-x: hidden;
            overscroll-behavior: contain;
            scrollbar-width: thin;
            scrollbar-color: #9fb6e5 #eef4ff;
        }

        .filter-select-dropdown::-webkit-scrollbar {
            width: 8px;
        }

        .filter-select-dropdown::-webkit-scrollbar-track {
            background: #eef4ff;
            border-radius: 999px;
        }

        .filter-select-dropdown::-webkit-scrollbar-thumb {
            background: #9fb6e5;
            border-radius: 999px;
        }

        .filter-select-option {
            display: block;
            width: 100%;
            border: 0;
            background: transparent;
            color: #24304e;
            padding: 9px 16px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 500;
            line-height: 1.35;
            text-align: left;
            cursor: pointer;
            transition: background 0.16s ease, color 0.16s ease;
        }

        .filter-select-option:hover {
            background: #eef4ff;
        }

        .filter-select-option.active {
            background: linear-gradient(180deg, var(--header), var(--header-dark));
            color: #ffffff;
        }

        .dropdown-backdrop[hidden] {
            display: none;
        }

        .dropdown-backdrop {
            position: fixed;
            inset: 0 0 0 var(--sidebar-width);
            z-index: 30;
            background: rgba(18, 24, 38, 0.24);
        }

        .filter-input {
            width: 100%;
            height: 44px;
            border: 1px solid #cbdaf1;
            border-radius: 14px;
            padding: 0 14px;
            background: #ffffff;
            color: #24304e;
            font-size: 14px;
            line-height: 44px;
            text-align: center;
        }

        .filter-search-field .filter-input {
            padding-left: 42px;
            text-align: left;
        }

        .filter-input::placeholder {
            color: #aebbd5;
        }

        .date-trigger {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            height: 44px;
            border: 1px solid #cbdaf1;
            border-radius: 14px;
            padding: 0 12px;
            background: #ffffff;
            color: #24304e;
            font-size: 14px;
            line-height: 44px;
            cursor: pointer;
            text-align: center;
            padding-bottom: 1px;
        }

        .filter-select-trigger:focus-visible,
        .filter-input:focus,
        .date-trigger:focus-visible {
            outline: none;
            border-color: #98bbef;
            box-shadow: 0 0 0 3px rgba(24, 119, 242, 0.12);
        }

        .date-trigger:hover {
            background: #f7fbff;
        }

        .date-trigger-icon {
            position: relative;
            width: 18px;
            height: 18px;
            flex: 0 0 auto;
            border: 2px solid #7f95bf;
            border-radius: 4px;
            margin-top: 0;
        }

        .date-trigger-icon::before {
            content: "";
            position: absolute;
            left: 2px;
            right: 2px;
            top: 5px;
            height: 2px;
            background: #9ca5bf;
        }

        .date-trigger-icon::after {
            content: "";
            position: absolute;
            left: 3px;
            top: -5px;
            width: 8px;
            height: 6px;
            border-left: 2px solid #9ca5bf;
            border-right: 2px solid #9ca5bf;
        }

        .date-trigger-text {
            flex: 1 1 auto;
            display: block;
            min-width: 0;
            line-height: 44px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            text-align: center;
        }

        #employeeFilterText {
            display: block;
            min-height: 0;
            line-height: 44px;
        }

        .date-picker[hidden] {
            display: none;
        }

        .date-picker {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            z-index: 30;
            width: 560px;
            border: 1px solid #dbe2f0;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 26px 60px rgba(36, 28, 66, 0.16);
            overflow: hidden;
        }

        .date-picker-top {
            display: grid;
            grid-template-columns: 44px 1fr 1fr 44px;
            gap: 0;
            align-items: start;
            padding: 18px 18px 0;
        }

        .calendar-nav {
            width: 28px;
            height: 28px;
            margin-top: 8px;
            border: 1px solid #dde4f1;
            border-radius: 6px;
            background: #ffffff;
            color: #a1a8ba;
            cursor: pointer;
            font-size: 16px;
            line-height: 1;
        }

        .calendar-nav:hover {
            background: #f7f9fd;
        }

        .month-box {
            padding: 0 14px 16px;
        }

        .month-box + .month-box {
            border-left: 1px solid #e7ebf4;
        }

        .month-title {
            margin-bottom: 12px;
            text-align: center;
            color: #545d77;
            font-size: 16px;
            font-weight: 600;
        }

        .weekdays,
        .month-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 4px;
        }

        .weekday {
            text-align: center;
            color: #b0b6c6;
            font-size: 12px;
            padding-bottom: 4px;
        }

        .calendar-empty {
            height: 32px;
        }

        .calendar-day {
            height: 32px;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: #5f667b;
            font-size: 14px;
            cursor: pointer;
        }

        .calendar-day:hover {
            background: #eef5ff;
        }

        .calendar-day.in-range {
            background: #edf4ff;
            border-radius: 0;
        }

        .calendar-day.range-start,
        .calendar-day.range-end {
            background: linear-gradient(180deg, #59a8ff, #1877f2);
            color: #ffffff;
            border-radius: 0;
        }

        .calendar-day.range-start {
            border-radius: 8px 0 0 8px;
        }

        .calendar-day.range-end {
            border-radius: 0 8px 8px 0;
        }

        .calendar-day.range-start.range-end {
            border-radius: 8px;
        }

        .date-picker-bottom {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
            padding: 18px;
            border-top: 1px solid #e7ebf4;
            background: #fcfdff;
        }

        .date-inputs {
            display: flex;
            gap: 12px;
        }

        .date-input-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .date-input-group span {
            color: #b0b6c6;
            font-size: 12px;
        }

        .date-input {
            width: 110px;
            height: 36px;
            border: 0;
            border-radius: 0;
            padding: 0 10px;
            background: #f2f4f8;
            color: #697188;
            font-size: 14px;
        }

        .date-picker-actions {
            display: flex;
            gap: 10px;
        }

        .date-picker-button {
            height: 36px;
            border: 0;
            border-radius: 0;
            padding: 0 16px;
            font-size: 14px;
            cursor: pointer;
        }

        .date-picker-button.secondary {
            background: #edf3fb;
            color: #7b87a4;
        }

        .date-picker-button.primary {
            background: linear-gradient(180deg, #59a8ff, #1877f2);
            color: #ffffff;
        }

        .table-wrap {
            overflow: auto;
        }

        .table-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 12px 18px 16px;
            border-top: 1px solid #e3e8f3;
            background: linear-gradient(180deg, #fbfdff, #f4f8ff);
        }

        .table-pagination[hidden] {
            display: none;
        }

        .table-pagination-summary {
            color: #7180a2;
            font-size: 13px;
            white-space: nowrap;
            margin-left: auto;
            text-align: right;
        }

        .table-pagination-controls {
            display: inline-flex;
            align-items: center;
            justify-content: flex-start;
            gap: 8px;
            flex-wrap: wrap;
        }

        .pagination-button {
            min-width: 38px;
            height: 36px;
            border: 1px solid #cbdaf1;
            border-radius: 12px;
            padding: 0 12px;
            background: #ffffff;
            color: #47608f;
            font-size: 13px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: background 0.18s ease, border-color 0.18s ease, color 0.18s ease;
        }

        .pagination-button:hover:not(:disabled) {
            border-color: #8ab2eb;
            background: #edf4ff;
        }

        .pagination-button.active {
            border-color: transparent;
            background: linear-gradient(180deg, var(--header), var(--header-dark));
            color: #ffffff;
        }

        .pagination-button:disabled {
            opacity: 0.46;
            cursor: default;
        }

        .pagination-ellipsis {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 22px;
            height: 36px;
            color: #8b97b0;
            font-size: 16px;
            font-weight: 700;
            user-select: none;
        }

        table {
            width: 100%;
            min-width: 1100px;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .calls-table {
            min-width: 1100px;
        }

        thead tr {
            background: linear-gradient(180deg, var(--header), var(--header-dark));
            color: #ffffff;
        }

        th,
        td {
            border-right: 1px solid var(--grid);
            border-bottom: 1px solid var(--grid);
            vertical-align: middle;
        }

        th {
            padding: 14px 12px;
            position: relative;
        }

        td {
            padding: 6px 12px;
        }

        th:last-child,
        td:last-child {
            border-right: 0;
        }

        th {
            font-size: 15px;
            font-weight: 600;
            text-align: center;
        }

        .calls-table th[data-call-column] {
            padding-right: 20px;
        }

        .column-resizer {
            position: absolute;
            top: 0;
            right: -5px;
            z-index: 4;
            width: 10px;
            height: 100%;
            border: 0;
            padding: 0;
            background: transparent;
            cursor: col-resize;
            touch-action: none;
        }

        .column-resizer::after {
            content: "";
            position: absolute;
            top: 8px;
            bottom: 8px;
            left: 4px;
            width: 2px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.72);
            opacity: 0;
            transition: opacity 0.14s ease, background 0.14s ease;
        }

        .column-resizer:hover::after,
        .column-resizer:focus-visible::after,
        .calls-table.is-resizing .column-resizer::after {
            opacity: 1;
        }

        .column-resizer:focus-visible {
            outline: 2px solid rgba(255, 255, 255, 0.85);
            outline-offset: -3px;
        }

        body.is-resizing-columns {
            cursor: col-resize;
            user-select: none;
        }

        .sort-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            border: 0;
            padding: 0;
            background: transparent;
            color: inherit;
            font: inherit;
            cursor: pointer;
        }

        .sort-button::after {
            content: "↕";
            font-size: 13px;
            opacity: 0.72;
        }

        .sort-button.active.asc::after {
            content: "↑";
            opacity: 1;
        }

        .sort-button.active.desc::after {
            content: "↓";
            opacity: 1;
        }

        tbody tr {
            background: var(--row);
            cursor: pointer;
            transition: background 0.16s ease;
        }

        tbody tr:nth-child(even) {
            background: var(--row-alt);
        }

        tbody tr:hover,
        tbody tr.active {
            background: var(--row-active);
        }

        .dir-cell {
            width: 56px;
            text-align: center;
        }

        .interaction-count-cell,
        .interaction-number-cell {
            width: 96px;
            text-align: center;
            font-variant-numeric: tabular-nums;
        }

        .interaction-number-cell {
            width: 112px;
        }

        .interaction-count-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 32px;
            border: 0;
            padding: 0 10px;
            border-radius: 999px;
            background: #eef4ff;
            color: #2563eb;
            font-family: inherit;
            font-size: 16px;
            font-weight: 800;
            line-height: 1;
        }

        a.interaction-count-badge,
        button.interaction-count-badge {
            cursor: pointer;
            text-decoration: none;
            transition: background 0.16s ease, color 0.16s ease, transform 0.16s ease;
        }

        a.interaction-count-badge:hover,
        button.interaction-count-badge:hover {
            background: #dbeafe;
            color: #1d4ed8;
            transform: translateY(-1px);
        }

        body.is-interaction-history-page .sidebar,
        body.is-interaction-history-page .card-side,
        body.is-interaction-history-page .table-pagination {
            display: none;
        }

        body.is-interaction-history-page .app-shell {
            display: block;
            min-height: 100vh;
        }

        body.is-interaction-history-page .main-area {
            width: 100%;
            padding: 0;
        }

        body.is-interaction-history-page .calls-page {
            width: 100%;
            min-height: 100vh;
            margin: 0;
        }

        body.is-interaction-history-page .table-card {
            width: 100%;
            min-height: 100vh;
        }

        body.is-interaction-history-page .table-wrap {
            width: 100%;
        }

        body.is-interaction-history-page .calls-table {
            width: 100% !important;
            min-width: 100% !important;
        }

        body.is-interaction-history-page .calls-table col {
            width: auto !important;
        }

        body.is-interaction-history-page tbody tr,
        body.is-interaction-history-page .score-chip,
        body.is-interaction-history-page .icon-button {
            cursor: default;
        }

        .caller-cell {
            width: 280px;
        }

        .model-cell {
            width: 190px;
        }

        .employee-cell {
            width: 320px;
        }

        .duration-cell {
            width: 130px;
            text-align: center;
            font-variant-numeric: tabular-nums;
            font-size: 16px;
        }

        .time-cell {
            width: 160px;
            text-align: center;
        }

        .action-cell,
        .score-cell {
            width: 94px;
            text-align: center;
        }

        .dir-indicator {
            display: inline-block;
            width: 20px;
            height: 20px;
            background-position: center;
            background-repeat: no-repeat;
            background-size: 18px 18px;
            transform-origin: 50% 50%;
        }

        .dir-in {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 18 18' fill='none' stroke='%2363c38f' stroke-width='4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M4 14L10 8'/%3E%3Cpath d='M8 4H14V10'/%3E%3C/svg%3E");
            transform: rotate(180deg);
        }

        .dir-out {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 18 18' fill='none' stroke='%235da9ea' stroke-width='4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M4 14L10 8'/%3E%3Cpath d='M8 4H14V10'/%3E%3C/svg%3E");
            transform: none;
        }

        .main-text {
            font-size: 16px;
            font-weight: 700;
            color: #0f1830;
            line-height: 1.25;
        }

        .sub-text {
            display: block;
            margin-top: 4px;
            color: #8b91a3;
            font-size: 13px;
            line-height: 1.35;
        }

        .badge {
            display: inline-block;
            margin-left: 8px;
            padding: 2px 6px;
            border-radius: 4px;
            background: #8dacdf;
            color: #ffffff;
            font-size: 11px;
            vertical-align: middle;
        }

        .time-main {
            display: block;
            font-size: 16px;
            color: #7b8196;
        }

        .time-sub {
            display: block;
            margin-top: 4px;
            font-size: 13px;
            color: #9aa0b1;
        }

        .icon-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border: 1px solid #d3ddf1;
            border-radius: 12px;
            background: #ffffff;
            cursor: pointer;
            transition: transform 0.14s ease, background 0.14s ease, border-color 0.14s ease;
        }

        .icon-button:hover {
            transform: translateY(-1px);
            background: #f4f8ff;
            border-color: #bdd0f0;
        }

        .icon-button:focus-visible,
        .interaction-count-badge:focus-visible,
        .score-chip:focus-visible,
        .modal-close:focus-visible,
        .date-trigger:focus-visible,
        .sort-button:focus-visible,
        .calendar-day:focus-visible {
            outline: 2px solid #7eaef2;
            outline-offset: 2px;
        }

        .bubble-icon,
        .sound-icon {
            position: relative;
            display: inline-block;
        }

        .bubble-icon {
            width: 16px;
            height: 12px;
            border-radius: 2px;
            background: #9cadce;
        }

        .bubble-icon::after {
            content: "";
            position: absolute;
            left: 3px;
            bottom: -4px;
            width: 6px;
            height: 6px;
            background: #9cadce;
            clip-path: polygon(0 0, 100% 0, 0 100%);
        }

        .sound-icon {
            width: 16px;
            height: 16px;
        }

        .sound-icon::before {
            content: "";
            position: absolute;
            left: 0;
            top: 4px;
            width: 6px;
            height: 8px;
            background: #9cadce;
            clip-path: polygon(0 28%, 48% 28%, 100% 0, 100% 100%, 48% 72%, 0 72%);
        }

        .sound-icon::after {
            content: "";
            position: absolute;
            left: 9px;
            top: 3px;
            width: 6px;
            height: 10px;
            border: 2px solid #9cadce;
            border-left: 0;
            border-radius: 0 9px 9px 0;
        }

        .score-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 58px;
            height: 38px;
            border: 0;
            border-radius: 999px;
            padding: 0 14px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
        }

        .score-high {
            background: var(--score-high-bg);
            color: var(--score-high-fg);
        }

        .score-mid {
            background: var(--score-mid-bg);
            color: var(--score-mid-fg);
        }

        .score-low {
            background: var(--score-low-bg);
            color: var(--score-low-fg);
        }

        .modal-overlay[hidden] {
            display: none;
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 28px;
            background: rgba(23, 19, 40, 0.62);
            backdrop-filter: blur(5px);
        }

        body.is-page-scroll-locked {
            position: fixed;
            left: 0;
            right: 0;
            width: 100%;
            overflow-y: scroll;
        }

        .modal-card {
            width: min(760px, 100%);
            max-height: 88vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border-radius: 22px;
            background: #ffffff;
            box-shadow: 0 26px 80px rgba(17, 14, 32, 0.3);
        }

        .modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            padding: 22px 24px 18px;
            border-bottom: 1px solid #e6ebf5;
            background: linear-gradient(180deg, #fcfdff, #f2f7ff);
        }

        .modal-kicker {
            margin-bottom: 8px;
            color: #8b91a3;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.16em;
        }

        .modal-title {
            margin: 0;
            font-size: 26px;
            line-height: 1.2;
        }

        .modal-subtitle {
            margin: 8px 0 0;
            color: #697188;
            font-size: 14px;
            line-height: 1.6;
        }

        .modal-close {
            flex: 0 0 auto;
            width: 42px;
            height: 42px;
            border: 0;
            border-radius: 999px;
            background: #edf4ff;
            color: #5575a8;
            cursor: pointer;
            font-size: 24px;
            line-height: 1;
        }

        .modal-body {
            padding: 22px 24px 24px;
            overflow: auto;
        }

        .modal-grid {
            display: grid;
            gap: 16px;
        }

        .modal-box {
            border: 1px solid #e5eaf4;
            border-radius: 18px;
            padding: 18px;
            background: #fbfcff;
        }

        .modal-box h3 {
            margin: 0 0 10px;
            font-size: 16px;
        }

        .modal-box p {
            margin: 0;
            color: #626a81;
            font-size: 14px;
            line-height: 1.7;
        }

        .transcript-text {
            white-space: pre-line;
        }

        .audio-wave {
            position: relative;
            height: 90px;
            border-radius: 16px;
            overflow: hidden;
            background: linear-gradient(180deg, #f1f7ff, #e5f0ff);
        }

        .audio-wave::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg,
                    rgba(24, 119, 242, 0.14) 0%,
                    rgba(24, 119, 242, 0.14) 7%,
                    transparent 7%,
                    transparent 11%,
                    rgba(24, 119, 242, 0.22) 11%,
                    rgba(24, 119, 242, 0.22) 16%,
                    transparent 16%,
                    transparent 23%,
                    rgba(24, 119, 242, 0.12) 23%,
                    rgba(24, 119, 242, 0.12) 27%,
                    transparent 27%,
                    transparent 35%,
                    rgba(24, 119, 242, 0.24) 35%,
                    rgba(24, 119, 242, 0.24) 39%,
                    transparent 39%,
                    transparent 47%,
                    rgba(24, 119, 242, 0.17) 47%,
                    rgba(24, 119, 242, 0.17) 52%,
                    transparent 52%,
                    transparent 60%,
                    rgba(24, 119, 242, 0.21) 60%,
                    rgba(24, 119, 242, 0.21) 65%,
                    transparent 65%,
                    transparent 73%,
                    rgba(24, 119, 242, 0.13) 73%,
                    rgba(24, 119, 242, 0.13) 77%,
                    transparent 77%,
                    transparent 85%,
                    rgba(24, 119, 242, 0.2) 85%,
                    rgba(24, 119, 242, 0.2) 90%,
                    transparent 90%,
                    transparent 100%);
        }

        .audio-wave::after {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 37%;
            background: linear-gradient(90deg, rgba(24, 119, 242, 0.18), rgba(24, 119, 242, 0.02));
            border-right: 2px solid rgba(24, 119, 242, 0.62);
        }

        .score-summary {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 16px;
            align-items: center;
        }

        .score-circle {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 112px;
            height: 112px;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, #ffcaab, #ff8d5d);
            color: #ffffff;
            font-size: 34px;
            font-weight: 700;
            box-shadow: inset 0 0 0 8px rgba(255, 255, 255, 0.24);
        }

        .score-list {
            display: grid;
            gap: 12px;
        }

        .score-item {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 14px;
            align-items: start;
            border: 1px solid #e5eaf4;
            border-radius: 16px;
            padding: 14px 16px;
            background: #fbfcff;
        }

        .score-item h4 {
            margin: 0 0 6px;
            font-size: 15px;
        }

        .score-item p {
            margin: 0;
            color: #677085;
            font-size: 14px;
            line-height: 1.55;
        }

        .score-meta {
            margin-top: 12px !important;
            color: #50627f !important;
            font-size: 13px !important;
            line-height: 1.65 !important;
        }

        .grid-two {
            display: grid;
            grid-template-columns: minmax(300px, 420px) minmax(420px, 1fr);
            gap: 24px;
        }

        .section-card {
            padding: 24px 24px 26px;
        }

        .section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
        }

        .section-head-actions {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            flex: 0 0 auto;
        }

        .section-head h2 {
            margin: 0;
            font-size: 24px;
        }

        .section-head p {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .primary-button,
        .ghost-button {
            height: 42px;
            border-radius: 14px;
            padding: 0 16px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .primary-button {
            border: 0;
            background: linear-gradient(180deg, #5eaaff, #1877f2);
            color: #ffffff;
        }

        .icon-button:disabled {
            opacity: 0.65;
            cursor: wait;
            transform: none;
        }

        .primary-button:disabled,
        .ghost-button:disabled {
            opacity: 0.65;
            cursor: wait;
        }

        .ghost-button {
            border: 1px solid #d7deec;
            background: #ffffff;
            color: #5b647d;
        }

        .checklist-toolbar-button svg {
            width: 18px;
            height: 18px;
            display: block;
            stroke: #5b6f95;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .checklist-toolbar-button:hover svg {
            stroke: #2468c9;
        }

        .stack-list {
            display: grid;
            gap: 12px;
        }

        .compact-table {
            min-width: 0;
        }

        .compact-table th {
            text-align: left;
            color: #ffffff;
        }

        .compact-table td {
            padding: 8px 14px;
        }

        .managers-name-col {
            width: 280px;
        }

        .managers-count-col,
        .managers-score-col {
            width: 180px;
            text-align: center;
        }

        .managers-score-value {
            font-size: 22px;
            font-weight: 700;
            color: var(--accent);
        }

        .managers-score-chip {
            min-width: 64px;
            cursor: default;
        }

        .managers-recommendation {
            color: #5f6882;
            font-size: 14px;
            line-height: 1.6;
        }

        .stack-item {
            position: relative;
            border: 1px solid #e2e7f1;
            border-radius: 18px;
            padding: 16px;
            background: #fbfcff;
            text-align: left;
            cursor: pointer;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .stack-item:focus-visible {
            outline: 2px solid #7eaef2;
            outline-offset: 2px;
        }

        .stack-item.is-active {
            border-color: #4d8cf6;
            background: #f5f9ff;
            box-shadow: 0 12px 28px rgba(77, 140, 246, 0.16);
        }

        .stack-item.is-renaming {
            cursor: default;
        }

        .stack-item-title-row {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 24px;
            padding-right: 84px;
        }

        .stack-item strong {
            display: block;
            flex: 1 1 auto;
            min-width: 0;
            font-size: 15px;
        }

        .stack-item-summary {
            display: block;
            margin-top: 6px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.55;
        }

        .stack-item-actions {
            position: absolute;
            top: 10px;
            right: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
            opacity: 0;
            pointer-events: none;
        }

        .stack-item:hover .stack-item-actions,
        .stack-item:focus-within .stack-item-actions,
        .stack-item:focus-visible .stack-item-actions {
            opacity: 1;
            pointer-events: auto;
        }

        .stack-item.is-renaming .stack-item-actions {
            display: none;
        }

        @media (hover: none) {
            .stack-item-actions {
                opacity: 1;
                pointer-events: auto;
            }
        }

        .stack-item-rename-button svg,
        .stack-item-delete-button svg {
            width: 16px;
            height: 16px;
            display: block;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .stack-item-rename-button svg {
            stroke: #5b6f95;
        }

        .stack-item-rename-button:hover svg {
            stroke: #2468c9;
        }

        .stack-item-delete-button svg {
            stroke: #c55367;
        }

        .stack-item-delete-button:hover svg {
            stroke: #a61d33;
        }

        .checklist-rename-input {
            height: 40px;
            padding: 0 12px;
            font-size: 15px;
            font-weight: 700;
        }

        .confirm-modal-card {
            max-width: 520px;
        }

        .checklist-export-modal-card {
            max-width: 540px;
        }

        .confirm-modal-body {
            display: grid;
            gap: 20px;
        }

        .checklist-export-modal-body {
            display: grid;
            gap: 16px;
        }

        .checklist-export-actions {
            display: grid;
            gap: 12px;
        }

        .checklist-export-actions .primary-button,
        .checklist-export-actions .ghost-button {
            width: 100%;
            height: auto;
            min-height: 48px;
            white-space: normal;
            line-height: 1.35;
        }

        .confirm-modal-message {
            margin: 0;
            color: #4f5972;
            font-size: 15px;
            line-height: 1.65;
        }

        .confirm-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .transcription-ai-modal-card {
            max-width: 720px;
        }

        .transcription-ai-form {
            display: grid;
            gap: 18px;
        }

        .transcription-ai-prompt {
            min-height: 220px;
        }

        .transcription-llm-system-prompt {
            min-height: 180px;
        }

        .transcription-ai-local-settings {
            display: grid;
            gap: 16px;
            border: 1px solid #d7deec;
            border-radius: 20px;
            background: #f8fbff;
            padding: 18px;
        }

        .transcription-ai-local-settings-head {
            display: grid;
            gap: 6px;
        }

        .transcription-ai-local-settings-head h3 {
            margin: 0;
            color: #24304e;
            font-size: 16px;
            font-weight: 800;
        }

        .transcription-ai-local-settings-head p {
            margin: 0;
            color: #64718a;
            font-size: 13px;
            line-height: 1.5;
        }

        .transcription-ai-compact-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .transcription-ai-help {
            margin: 0;
            color: #64718a;
            font-size: 14px;
            line-height: 1.6;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(220px, 1fr));
            gap: 16px;
        }

        .form-grid.single {
            grid-template-columns: 1fr;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-field label {
            color: #687086;
            font-size: 13px;
            font-weight: 600;
        }

        .text-input,
        .text-select,
        .textarea-input {
            width: 100%;
            border: 1px solid #d7deec;
            border-radius: 14px;
            background: #ffffff;
            color: #24304e;
            font-size: 14px;
        }

        .text-input,
        .text-select {
            height: 46px;
            padding: 0 14px;
        }

        .textarea-input {
            min-height: 150px;
            padding: 14px;
            resize: vertical;
            line-height: 1.6;
        }

        .checklist-items-board {
            border: 1px solid #d7deec;
            border-radius: 18px;
            background: #fbfcff;
            padding: 14px;
        }

        .checklist-items-head {
            display: grid;
            grid-template-columns: 44px minmax(0, 1fr) 108px 48px;
            gap: 12px;
            align-items: center;
            margin-bottom: 10px;
            padding: 0 4px;
            color: #7a88a6;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .checklist-items-editor {
            display: grid;
            gap: 10px;
        }

        .checklist-item-row {
            display: grid;
            grid-template-columns: 44px minmax(0, 1fr) 108px 48px;
            gap: 12px;
            align-items: start;
        }

        .checklist-item-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: #eef3ff;
            color: #5f6f95;
            font-size: 14px;
            font-weight: 800;
            line-height: 1;
            cursor: grab;
            user-select: none;
        }

        .checklist-item-number:active {
            cursor: grabbing;
        }

        .checklist-item-row.is-dragging {
            opacity: 0.62;
        }

        .checklist-item-row.is-drop-before {
            box-shadow: inset 0 3px 0 #5da9ea;
        }

        .checklist-item-row.is-drop-after {
            box-shadow: inset 0 -3px 0 #5da9ea;
        }

        .checklist-item-label-input {
            min-height: 46px;
            height: 46px;
            padding: 12px 14px;
            resize: none;
            overflow: hidden;
            line-height: 1.45;
            transition: height 0.18s ease;
        }

        .checklist-item-label-input.is-expanded {
            overflow-y: auto;
        }

        .checklist-item-points-input {
            height: 46px;
            padding: 0 12px;
            text-align: center;
            font-weight: 700;
        }

        .checklist-item-add-button {
            width: 48px;
            min-width: 48px;
            height: 46px;
            padding: 0;
            border-radius: 14px;
            font-size: 24px;
            line-height: 1;
        }

        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 20px;
        }

        .settings-feedback {
            display: flex;
            align-items: center;
            min-height: 46px;
            padding: 0 14px;
            border: 1px solid #d7deec;
            border-radius: 14px;
            background: #ffffff;
            color: #60708f;
            font-size: 14px;
        }

        .settings-feedback.is-loading {
            color: #2468c9;
            border-color: #bfd4f6;
            background: #f6faff;
        }

        .settings-feedback.is-success {
            color: #14824a;
            border-color: #c7ead5;
            background: #f3fcf7;
        }

        .settings-feedback.is-error {
            color: #c83c53;
            border-color: #f4c9d2;
            background: #fff6f8;
        }

        .range-setting {
            border: 1px solid #d7deec;
            border-radius: 16px;
            background: #fbfcff;
            padding: 14px 16px 12px;
        }

        .range-setting-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
        }

        .range-setting-value {
            color: #1f4f99;
            font-size: 14px;
            font-weight: 700;
            white-space: nowrap;
        }

        .settings-range-input {
            width: 100%;
            margin: 0;
            accent-color: #2f84f6;
            cursor: pointer;
        }

        .range-setting-limits {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 8px;
            color: #7b89a7;
            font-size: 12px;
            line-height: 1.2;
        }

        .transcription-page {
            max-width: none;
            margin: -22px -28px;
            min-height: calc(100vh - 44px);
            gap: 0;
        }

        .transcription-shell {
            min-height: calc(100vh - 44px);
            border: 0;
            border-radius: 0;
            box-shadow: none;
            background: transparent;
        }

        .transcription-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 20px 28px 16px;
            border-bottom: 1px solid #dfe6f2;
            background: linear-gradient(180deg, #f7f9fe, #f1f5fc);
        }

        .transcription-head h1 {
            margin: 0;
            font-size: 34px;
            letter-spacing: -0.02em;
            line-height: 1.05;
        }

        .transcription-subtitle {
            margin: 0;
            color: #6f7f9f;
            font-size: 15px;
        }

        .transcription-layout {
            display: grid;
            grid-template-columns: minmax(420px, 0.9fr) minmax(420px, 1.1fr);
            gap: 24px;
            padding: 20px 22px 24px;
            align-items: stretch;
        }

        .transcription-panel {
            border: 1px solid #dce5f3;
            border-radius: 24px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            box-shadow: 0 16px 28px rgba(24, 74, 148, 0.06);
            padding: 24px;
        }

        .transcription-panel h2 {
            margin: 0 0 8px;
            font-size: 26px;
            letter-spacing: -0.02em;
            line-height: 1.1;
        }

        .transcription-upload-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 20px;
        }

        .transcription-upload-copy {
            min-width: 0;
        }

        .transcription-upload-copy h2 {
            margin-bottom: 8px;
        }

        .transcription-upload-copy p {
            margin: 0;
            color: #62718f;
            font-size: 15px;
            line-height: 1.45;
        }

        .transcription-run-icon-button {
            width: 58px;
            height: 58px;
            flex: 0 0 58px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border-radius: 18px;
            box-shadow: 0 14px 26px rgba(24, 119, 242, 0.22);
        }

        .transcription-run-icon-button svg {
            width: 23px;
            height: 23px;
            display: block;
            fill: currentColor;
            transform: translateX(2px);
        }

        .transcription-run-icon-button.is-running svg {
            animation: transcription-run-pulse 0.85s ease-in-out infinite alternate;
        }

        @keyframes transcription-run-pulse {
            from {
                opacity: 1;
                transform: translateX(2px) scale(1);
            }

            to {
                opacity: 0.55;
                transform: translateX(2px) scale(0.82);
            }
        }

        .transcription-result-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }

        .transcription-result-head h2 {
            margin-bottom: 0;
        }

        .transcription-result-toolbar {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            flex: 0 0 auto;
        }

        .transcription-ai-button,
        .transcription-ai-settings-button {
            width: 44px;
            height: 44px;
            border-radius: 14px;
        }

        .transcription-ai-button-label {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
            min-height: 24px;
            border-radius: 999px;
            background: linear-gradient(180deg, #dff0ff 0%, #c0dcff 100%);
            color: #2758a5;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.08em;
        }

        .transcription-ai-settings-button svg {
            width: 18px;
            height: 18px;
            display: block;
            stroke: #5b6f95;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .transcription-ai-settings-button:hover svg {
            stroke: #2468c9;
        }

        .transcription-ai-stop-button {
            border-color: #f0c1ca;
            background: #fff5f7;
        }

        .transcription-ai-stop-button:hover {
            background: #ffecef;
            border-color: #e6a9b6;
        }

        .transcription-ai-stop-button svg {
            width: 18px;
            height: 18px;
            display: block;
            fill: #cb3a5b;
            stroke: none;
        }

        .transcription-panel > p {
            margin: 0 0 20px;
            color: #62718f;
            font-size: 15px;
            line-height: 1.45;
        }

        .transcription-upload-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(220px, 1fr));
            gap: 14px;
            margin-bottom: 16px;
        }

        .transcription-upload-card {
            border: 1px dashed #bcd0ec;
            border-radius: 18px;
            background: #f3f8ff;
            padding: 16px;
            min-height: 148px;
            display: flex;
            flex-direction: column;
        }

        .transcription-upload-card h3 {
            margin: 0 0 14px;
            font-size: 15px;
            line-height: 1.15;
            letter-spacing: -0.01em;
            color: #1c2a45;
        }

        .transcription-upload-card p {
            margin: 0;
            color: #6f7f9f;
            font-size: 15px;
            line-height: 1.45;
        }

        .file-input-wrap {
            margin-top: auto;
            display: block;
        }

        .transcription-file-control {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .transcription-file-input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .transcription-file-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 168px;
            width: 168px;
            height: 56px;
            padding: 0 20px;
            border: 1px solid #bfd1eb;
            border-radius: 16px;
            background: #ffffff;
            color: #274169;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
        }

        .transcription-file-name {
            min-width: 0;
            flex: 1 1 auto;
            color: #6f7f9f;
            font-size: 15px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            text-align: left;
        }

        .transcription-upload-card .text-input {
            height: 56px;
            border: 1px solid #cfdcf0;
            border-radius: 16px;
            padding: 0 18px;
            font-size: 17px;
            background: #ffffff;
            width: 100%;
        }

        .transcription-input-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(220px, 1fr));
            gap: 12px;
            margin-bottom: 12px;
        }

        .transcription-field label {
            display: block;
            margin-bottom: 10px;
            color: #5b6f94;
            font-size: 16px;
            font-weight: 700;
        }

        .transcription-field .text-input,
        .transcription-field .text-select {
            height: 56px;
            border: 1px solid #cfdcf0;
            border-radius: 16px;
            font-size: 17px;
            padding: 0 18px;
            background: #ffffff;
        }

        .transcription-toggle {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-top: 4px;
            color: #3f5076;
            font-size: 15px;
            font-weight: 600;
            user-select: none;
        }

        .transcription-toggle input {
            width: 16px;
            height: 16px;
            accent-color: #1877f2;
        }

        .transcription-checklist-row {
            margin-top: 14px;
            width: calc(50% - 6px);
            max-width: none;
        }

        .transcription-checklist-row .text-select {
            height: 56px;
            border-radius: 16px;
            border: 1px solid #cfdcf0;
            font-size: 17px;
            padding: 0 18px;
            background: #ffffff;
        }

        .transcription-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .transcription-actions .primary-button,
        .transcription-actions .ghost-button {
            height: 48px;
            padding: 0 22px;
            border-radius: 15px;
            font-size: 16px;
        }

        .transcription-actions .ghost-button {
            border-color: #cfdaeb;
            color: #4d5f84;
            background: #ffffff;
        }

        .transcription-feedback {
            flex: 1 1 100%;
            min-height: 22px;
            color: #5c6f95;
            font-size: 14px;
            line-height: 1.5;
        }

        .transcription-feedback.is-loading {
            color: #2b6ed4;
        }

        .transcription-feedback.is-success {
            color: #208656;
        }

        .transcription-feedback.is-error {
            color: #cb3a5b;
        }

        .transcription-checklist-row.is-disabled {
            opacity: 0.55;
        }

        .transcription-result-panel {
            display: grid;
            grid-template-rows: auto auto 1fr;
            gap: 14px;
        }

        .transcription-result-box {
            border: 1px solid #d5e0f2;
            border-radius: 18px;
            background: #ffffff;
            min-height: 290px;
            padding: 14px;
        }

        .transcription-ai-live-box,
        .transcription-ai-input-box {
            border: 1px solid #d5e0f2;
            border-radius: 18px;
            background: #ffffff;
            padding: 16px;
            display: grid;
            gap: 12px;
            align-content: start;
        }

        .transcription-ai-live-box[hidden],
        .transcription-ai-input-box[hidden] {
            display: none;
        }

        .transcription-ai-live-close-button,
        .transcription-ai-input-close-button {
            width: 38px;
            height: 38px;
            color: #72809d;
            font-size: 24px;
            font-weight: 700;
            line-height: 1;
        }

        .transcription-ai-live-close-button:hover,
        .transcription-ai-input-close-button:hover {
            color: #cb3a5b;
            border-color: #e6a9b6;
            background: #fff5f7;
        }

        .transcription-ai-input-console {
            max-height: 440px;
        }

        .transcription-result-text {
            width: 100%;
            min-height: 260px;
            border: 0;
            resize: vertical;
            color: #2a3859;
            font-size: 17px;
            line-height: 1.8;
            font-family: "Segoe UI", Arial, sans-serif;
            background: transparent;
        }

        .transcription-result-text:focus {
            outline: none;
        }

        .transcription-score-box {
            border: 1px solid #d5e0f2;
            border-radius: 18px;
            background: #ffffff;
            padding: 16px;
            display: grid;
            gap: 12px;
            align-content: start;
        }

        .transcription-llm-box {
            border: 1px solid #d5e0f2;
            border-radius: 18px;
            background: #ffffff;
            padding: 16px;
            display: grid;
            gap: 12px;
            align-content: start;
        }

        .transcription-llm-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 112px;
            min-height: 36px;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 700;
            line-height: 1.2;
            text-align: center;
            background: #eef3fb;
            color: #6a7b9b;
        }

        .transcription-llm-status.is-pending {
            background: #eef3fb;
            color: #6a7b9b;
        }

        .transcription-llm-status.is-running {
            background: #e9f2ff;
            color: #2b6ed4;
        }

        .transcription-llm-status.is-completed {
            background: #e7f7ec;
            color: #1d8d58;
        }

        .transcription-llm-status.is-failed {
            background: #fdecef;
            color: #cb3a5b;
        }

        .transcription-llm-console {
            margin: 0;
            border: 1px solid #dfe7f5;
            border-radius: 16px;
            background: #f7faff;
            padding: 14px 16px;
            max-height: 360px;
            overflow: auto;
            color: #334565;
            font-size: 14px;
            line-height: 1.6;
            white-space: pre-wrap;
            word-break: break-word;
            font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
        }

        .transcription-llm-toggle {
            width: 100%;
            justify-content: space-between;
            padding: 0 14px;
        }

        .transcription-llm-toggle::after {
            content: "▾";
            font-size: 16px;
            line-height: 1;
            transition: transform 0.2s ease;
        }

        .transcription-llm-toggle[aria-expanded="true"]::after {
            transform: rotate(180deg);
        }

        .transcription-llm-prompt-details {
            border-top: 1px solid #e4ecf8;
            padding-top: 4px;
        }

        .transcription-llm-prompt-text {
            margin: 0;
            border: 1px solid #dfe7f5;
            border-radius: 16px;
            background: #fbfcff;
            padding: 14px 16px;
            max-height: 360px;
            overflow: auto;
            color: #334565;
            font-size: 14px;
            line-height: 1.6;
            white-space: pre-wrap;
            word-break: break-word;
            font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
        }

        .transcription-score-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .transcription-score-head h3 {
            margin: 0;
            font-size: 24px;
            line-height: 1.2;
        }

        .transcription-score-meta {
            display: grid;
            gap: 7px;
            color: #546686;
            font-size: 15px;
            line-height: 1.5;
        }

        .transcription-score-line {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 14px;
            background: #f5f8fd;
        }

        .transcription-score-label {
            color: #62718f;
            font-size: 14px;
            font-weight: 600;
        }

        .transcription-score-toggle {
            width: 100%;
            justify-content: space-between;
            padding: 0 14px;
        }

        .transcription-score-toggle::after {
            content: "▾";
            font-size: 16px;
            line-height: 1;
            transition: transform 0.2s ease;
        }

        .transcription-score-toggle[aria-expanded="true"]::after {
            transform: rotate(180deg);
        }

        .transcription-score-details {
            border-top: 1px solid #e4ecf8;
            padding-top: 4px;
        }

        .transcription-score-items {
            display: grid;
            gap: 10px;
            max-height: 320px;
            overflow-y: auto;
            padding-right: 4px;
        }

        .transcription-score-item {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 14px;
            align-items: start;
            border: 1px solid #e5eaf4;
            border-radius: 16px;
            padding: 14px 16px;
            background: #fbfcff;
        }

        .transcription-score-item-main {
            min-width: 0;
        }

        .transcription-score-item-title {
            margin: 0 0 6px;
            font-size: 15px;
            line-height: 1.35;
        }

        .transcription-score-item-comment {
            margin: 0;
            color: #677085;
            font-size: 14px;
            line-height: 1.55;
        }

        .transcription-score-item .score-chip {
            min-width: 54px;
            height: 36px;
            font-size: 17px;
            cursor: default;
        }

        .transcription-score-item .score-chip.is-muted {
            background: #eef3fb;
            color: #7081a2;
        }

        @media (max-width: 900px) {
            .app-shell {
                display: block;
            }

            .sidebar {
                width: 100%;
            }

            .sidebar-inner {
                height: auto;
                position: static;
            }

            .main-area {
                padding: 18px;
            }

            .calls-page,
            .managers-page {
                margin: -18px;
                min-height: calc(100vh - 36px);
            }

            .calls-page .table-card,
            .managers-page .table-card {
                min-height: calc(100vh - 36px);
            }

            .card-head,
            .grid-two,
            .form-grid,
            .transcription-ai-compact-grid {
                flex-direction: column;
                grid-template-columns: 1fr;
                align-items: flex-start;
            }

            .checklist-items-head,
            .checklist-item-row {
                grid-template-columns: 40px minmax(0, 1fr) 96px 44px;
            }

            .card-side {
                width: 100%;
                align-items: stretch;
            }

            .filter-panel {
                flex-wrap: wrap;
                justify-content: stretch;
                width: 100%;
            }

            .filter-field {
                min-width: 100%;
            }

            .transcription-page {
                margin: -18px;
                min-height: calc(100vh - 36px);
            }

            .transcription-shell {
                min-height: calc(100vh - 36px);
            }

            .transcription-head {
                padding: 16px;
            }

            .transcription-head h1 {
                font-size: 34px;
            }

            .transcription-layout {
                grid-template-columns: 1fr;
                padding: 16px;
            }

            .transcription-upload-grid,
            .transcription-input-grid {
                grid-template-columns: 1fr;
            }

            .transcription-checklist-row {
                width: 100%;
            }

            .transcription-panel h2 {
                font-size: 30px;
            }

            .transcription-panel > p {
                font-size: 15px;
            }

            .transcription-upload-card h3 {
                font-size: 15px;
            }

            .transcription-field .text-input,
            .transcription-field .text-select {
                font-size: 16px;
            }

            .transcription-checklist-row .text-select {
                font-size: 17px;
            }

            .transcription-actions .primary-button,
            .transcription-actions .ghost-button {
                font-size: 16px;
            }

            .transcription-result-text {
                font-size: 22px;
            }

            .transcription-score-head h3 {
                font-size: 28px;
            }

            .transcription-score-item {
                grid-template-columns: 1fr;
            }

            .transcription-score-item .score-chip {
                justify-self: start;
            }
        }
    </style>
</head>
<body>
<div class="app-shell" id="appShell">
    <aside class="sidebar">
        <div class="sidebar-inner">
            <div class="sidebar-top">
                <a class="sidebar-brand" href="{{ route('call-center') }}#callsSection" title="Відкрити розділ Дзвінки">
                    <span class="sidebar-brand-mark" aria-hidden="true">
                        <svg viewBox="0 0 64 64" role="img" aria-hidden="true">
                            <defs>
                                <linearGradient id="micFill" x1="0%" x2="100%" y1="0%" y2="100%">
                                    <stop offset="0%" stop-color="#58acff"></stop>
                                    <stop offset="100%" stop-color="#1877f2"></stop>
                                </linearGradient>
                            </defs>
                            <rect x="20" y="8" width="24" height="30" rx="12" fill="url(#micFill)"></rect>
                            <path d="M14 29c0 10 8 18 18 18s18-8 18-18" fill="none" stroke="url(#micFill)" stroke-linecap="round" stroke-width="6"></path>
                            <path d="M32 47v8" fill="none" stroke="url(#micFill)" stroke-linecap="round" stroke-width="6"></path>
                            <rect x="19" y="54" width="26" height="7" rx="3.5" fill="url(#micFill)"></rect>
                        </svg>
                    </span>
                    <span class="sidebar-brand-copy">
                        <span class="sidebar-brand-title">Кол-центр QA</span>
                        <span class="sidebar-brand-subtitle">Аналітика та оцінка</span>
                    </span>
                </a>
            </div>

            <nav class="sidebar-nav">
                <a class="nav-item active" data-section-target="callsSection" href="{{ route('call-center') }}#callsSection" title="Дзвінки">
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M5 4h3l2 5-2.5 1.5a13 13 0 0 0 5 5L14 13l5 2v3a2 2 0 0 1-2 2A15 15 0 0 1 4 7a2 2 0 0 1 1-3z"></path>
                        </svg>
                    </span>
                    <span class="nav-copy">
                        <span class="nav-title">Дзвінки</span>
                        <span class="nav-meta">Список, фільтри, модалки</span>
                    </span>
                </a>
                <a class="nav-item" data-section-target="managersSection" href="{{ route('call-center') }}#managersSection" title="Менеджери">
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <circle cx="9" cy="8" r="3"></circle>
                            <path d="M4.5 18c.8-2.8 2.7-4.2 4.5-4.2s3.7 1.4 4.5 4.2"></path>
                            <circle cx="17.5" cy="9" r="2.2"></circle>
                            <path d="M14.8 17.3c.5-1.9 1.8-3 3.3-3 1.2 0 2.3.7 3 2"></path>
                        </svg>
                    </span>
                    <span class="nav-copy">
                        <span class="nav-title">Менеджери</span>
                        <span class="nav-meta">Зведені оцінки та поради</span>
                    </span>
                </a>
                <a class="nav-item" data-section-target="checklistSection" href="{{ route('call-center') }}#checklistSection" title="Чек-лист">
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M9 6h10"></path>
                            <path d="M9 12h10"></path>
                            <path d="M9 18h10"></path>
                            <path d="M4 6l1.5 1.5L7.5 5.5"></path>
                            <path d="M4 12l1.5 1.5L7.5 11.5"></path>
                            <path d="M4 18l1.5 1.5L7.5 17.5"></path>
                        </svg>
                    </span>
                    <span class="nav-copy">
                        <span class="nav-title">Чек-лист</span>
                        <span class="nav-meta">Створення та редагування</span>
                    </span>
                </a>
                <a class="nav-item" data-section-target="transcriptionSection" href="{{ route('call-center') }}#transcriptionSection" title="Транскрибація 1">
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 4v10"></path>
                            <path d="M8 8v6"></path>
                            <path d="M16 6v8"></path>
                            <path d="M5 11v2"></path>
                            <path d="M19 9v6"></path>
                        </svg>
                    </span>
                    <span class="nav-copy">
                        <span class="nav-title">Транскрибація 1</span>
                        <span class="nav-meta">Файл, посилання, чек-листи</span>
                    </span>
                </a>
                <a class="nav-item" href="{{ route('alt.call-center') }}#transcriptionSection" title="Транскрибація 2">
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7z"></path>
                            <path d="M14 2v5h5"></path>
                            <path d="M8 15v-2"></path>
                            <path d="M11 17v-6"></path>
                            <path d="M14 15v-2"></path>
                            <path d="M17 16v-4"></path>
                        </svg>
                    </span>
                    <span class="nav-copy">
                        <span class="nav-title">Транскрибація 2</span>
                        <span class="nav-meta">Окремий контур</span>
                    </span>
                </a>
                <a class="nav-item" data-section-target="settingsSection" href="{{ route('call-center') }}#settingsSection" title="Налаштування">
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="3.2"></circle>
                            <path d="M12 3.5v2.2"></path>
                            <path d="M12 18.3v2.2"></path>
                            <path d="M4.9 6.1l1.6 1.6"></path>
                            <path d="M17.5 18.7l1.6 1.6"></path>
                            <path d="M3.5 12h2.2"></path>
                            <path d="M18.3 12h2.2"></path>
                            <path d="M4.9 17.9l1.6-1.6"></path>
                            <path d="M17.5 5.3l1.6-1.6"></path>
                        </svg>
                    </span>
                    <span class="nav-copy">
                        <span class="nav-title">Налаштування</span>
                        <span class="nav-meta">Доступ і Whisper</span>
                    </span>
                </a>
            </nav>

            <div class="sidebar-bottom">
                <a class="logout-link" href="{{ route('login') }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M10 6H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h4"></path>
                        <path d="M14 8l4 4-4 4"></path>
                        <path d="M9 12h9"></path>
                    </svg>
                    <span>Вийти</span>
                </a>
            </div>

        </div>
    </aside>

    <main class="main-area">
        <section class="content-section active" id="callsSection">
            <div class="page calls-page">
                <section class="table-card">
        <div class="card-head">
            <div class="card-head-main">
                <div class="card-title-box">
                    <h1 class="card-title">Список дзвінків</h1>
                </div>
            </div>
            <div class="card-side">
                <div class="filter-panel">
                    <label class="filter-field filter-search-field">
                        <span class="filter-label">Пошук за номером</span>
                        <input class="filter-input" id="phoneSearch" type="text" inputmode="tel" placeholder="Пошук за номером">
                    </label>
                    <div class="filter-field filter-select-field" id="employeeFilterField">
                        <span class="filter-label">Співробітник</span>
                        <select class="filter-select-native" id="employeeFilter" tabindex="-1" aria-hidden="true"></select>
                        <button type="button" class="filter-select-trigger" id="employeeFilterTrigger" aria-haspopup="listbox" aria-expanded="false">
                            <span id="employeeFilterText">Всі менеджери</span>
                        </button>
                        <div class="filter-select-dropdown" id="employeeFilterDropdown" role="listbox" hidden></div>
                    </div>
                    <div class="dropdown-backdrop" id="employeeFilterBackdrop" hidden></div>
                    <div class="filter-field filter-date-field">
                        <span class="filter-label">Дата</span>
                        <button type="button" class="date-trigger" id="dateRangeTrigger" aria-label="Обрати період">
                            <span class="date-trigger-icon" aria-hidden="true"></span>
                            <span class="date-trigger-text" id="dateRangeText">08.04.2026 - 08.04.2026</span>
                        </button>
                        <div class="date-picker" id="datePicker" hidden>
                            <div class="date-picker-top">
                                <button type="button" class="calendar-nav" id="calendarPrev" aria-label="Попередній місяць">‹</button>
                                <div class="month-box">
                                    <div class="month-title" id="monthTitleA"></div>
                                    <div class="weekdays" id="weekdaysA"></div>
                                    <div class="month-grid" id="monthGridA"></div>
                                </div>
                                <div class="month-box">
                                    <div class="month-title" id="monthTitleB"></div>
                                    <div class="weekdays" id="weekdaysB"></div>
                                    <div class="month-grid" id="monthGridB"></div>
                                </div>
                                <button type="button" class="calendar-nav" id="calendarNext" aria-label="Наступний місяць">›</button>
                            </div>
                            <div class="date-picker-bottom">
                                <div class="date-inputs">
                                    <label class="date-input-group">
                                        <span>Від</span>
                                        <input class="date-input" id="dateStartInput" type="text" readonly>
                                    </label>
                                    <label class="date-input-group">
                                        <span>До</span>
                                        <input class="date-input" id="dateEndInput" type="text" readonly>
                                    </label>
                                </div>
                                <div class="date-picker-actions">
                                    <button type="button" class="date-picker-button secondary" id="dateCancel">Скасувати</button>
                                    <button type="button" class="date-picker-button primary" id="dateApply">Застосувати</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-meta visually-hidden">
                    <strong id="activeDateLabel">08.04.2026 - 08.04.2026</strong>
                    <span id="callsCount">5 дзвінків</span>
                </div>
            </div>
        </div>

        <div class="table-wrap" id="callsTableWrap">
            <table class="calls-table" id="callsTable">
                <colgroup>
                    <col data-call-column="direction">
                    <col data-call-column="interactionCount">
                    <col data-call-column="interactionNumber">
                    <col data-call-column="caller">
                    <col data-call-column="model">
                    <col data-call-column="employee">
                    <col data-call-column="score">
                    <col data-call-column="duration">
                    <col data-call-column="time">
                    <col data-call-column="text">
                    <col data-call-column="audio">
                </colgroup>
                <thead>
                <tr>
                    <th class="dir-cell" data-call-column="direction">
                        <button type="button" class="column-resizer" data-call-column-resizer="direction" aria-label="Змінити ширину колонки напрямку" title="Перетягніть, щоб змінити ширину. Подвійний клік — скинути."></button>
                    </th>
                    <th class="interaction-count-cell" data-call-column="interactionCount">
                        <button type="button" class="sort-button" id="interactionCountSort" data-sort-field="interactionCount">
                            <span>Кількість</span>
                        </button>
                        <button type="button" class="column-resizer" data-call-column-resizer="interactionCount" aria-label="Змінити ширину колонки Кількість" title="Перетягніть, щоб змінити ширину. Подвійний клік — скинути."></button>
                    </th>
                    <th class="interaction-number-cell" data-call-column="interactionNumber">
                        <button type="button" class="sort-button" id="interactionNumberSort" data-sort-field="interactionNumber">
                            <span>Взаємодія</span>
                        </button>
                        <button type="button" class="column-resizer" data-call-column-resizer="interactionNumber" aria-label="Змінити ширину колонки Взаємодія" title="Перетягніть, щоб змінити ширину. Подвійний клік — скинути."></button>
                    </th>
                    <th class="caller-cell" data-call-column="caller">
                        Хто дзвонив
                        <button type="button" class="column-resizer" data-call-column-resizer="caller" aria-label="Змінити ширину колонки Хто дзвонив" title="Перетягніть, щоб змінити ширину. Подвійний клік — скинути."></button>
                    </th>
                    <th class="model-cell" data-call-column="model">
                        <button type="button" class="sort-button" id="modelSort" data-sort-field="model">Модель</button>
                        <button type="button" class="column-resizer" data-call-column-resizer="model" aria-label="Змінити ширину колонки Модель" title="Перетягніть, щоб змінити ширину. Подвійний клік — скинути."></button>
                    </th>
                    <th class="employee-cell" data-call-column="employee">
                        Кому дзвонили
                        <button type="button" class="column-resizer" data-call-column-resizer="employee" aria-label="Змінити ширину колонки Кому дзвонили" title="Перетягніть, щоб змінити ширину. Подвійний клік — скинути."></button>
                    </th>
                    <th class="score-cell" data-call-column="score">
                        <button type="button" class="sort-button" id="scoreSort" data-sort-field="score">Оцінка</button>
                        <button type="button" class="column-resizer" data-call-column-resizer="score" aria-label="Змінити ширину колонки Оцінка" title="Перетягніть, щоб змінити ширину. Подвійний клік — скинути."></button>
                    </th>
                    <th class="duration-cell" data-call-column="duration">
                        <button type="button" class="sort-button" id="durationSort" data-sort-field="duration">Тривалість</button>
                        <button type="button" class="column-resizer" data-call-column-resizer="duration" aria-label="Змінити ширину колонки Тривалість" title="Перетягніть, щоб змінити ширину. Подвійний клік — скинути."></button>
                    </th>
                    <th class="time-cell" data-call-column="time">
                        <button type="button" class="sort-button" id="timeSort" data-sort-field="time">Час дзвінка</button>
                        <button type="button" class="column-resizer" data-call-column-resizer="time" aria-label="Змінити ширину колонки Час дзвінка" title="Перетягніть, щоб змінити ширину. Подвійний клік — скинути."></button>
                    </th>
                    <th class="action-cell" data-call-column="text">
                        Текст
                        <button type="button" class="column-resizer" data-call-column-resizer="text" aria-label="Змінити ширину колонки Текст" title="Перетягніть, щоб змінити ширину. Подвійний клік — скинути."></button>
                    </th>
                    <th class="action-cell" data-call-column="audio">Аудіо</th>
                </tr>
                </thead>
                <tbody id="callsTableBody"></tbody>
            </table>
        </div>
        <div class="table-pagination" id="callsPagination" hidden></div>
                </section>
            </div>
        </section>

        <section class="content-section" id="managersSection">
            <div class="page managers-page">
                <section class="table-card">
                    <div class="card-head">
                        <div class="card-head-main">
                            <div class="card-title-box">
                                <h1 class="card-title">Зведення по менеджерах</h1>
                            </div>
                        </div>
                        <div class="card-side">
                            <div class="filter-panel">
                                <div class="filter-field filter-date-field">
                                    <span class="filter-label">Дата</span>
                                    <button type="button" class="date-trigger" aria-label="Фільтр за періодом">
                                        <span class="date-trigger-icon" aria-hidden="true"></span>
                                        <span class="date-trigger-text" id="managersDateRangeText">06.04.2026 - 08.04.2026</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table class="compact-table">
                            <thead>
                            <tr>
                                <th class="managers-name-col">Менеджер</th>
                                <th class="managers-count-col">Кількість дзвінків</th>
                                <th class="managers-score-col">Середня оцінка</th>
                                <th>Рекомендації</th>
                            </tr>
                            </thead>
                            <tbody id="managersTableBody"></tbody>
                        </table>
                    </div>
                    <div class="table-pagination" id="managersPagination" hidden></div>
                </section>
            </div>
        </section>

        <section class="content-section" id="checklistSection">
            @php
                $activeChecklist = collect($checklists)->firstWhere('id', $defaultChecklistId) ?? ($checklists[0] ?? [
                    'id' => '',
                    'name' => 'Новий чек-лист',
                    'type' => 'Загальний сценарій',
                    'summary' => '',
                    'items' => [
                        ['label' => 'Представився та окреслив мету дзвінка', 'max_points' => 10],
                    ],
                ]);
            @endphp
            <div class="page">
                <div class="grid-two">
                    <section class="table-card section-card">
                        <div class="section-head">
                            <div>
                                <div class="card-kicker">Чек-лист</div>
                                <h2>Набори критеріїв</h2>
                                <p>Тут зберігаються чек-листи для оцінювання дзвінків через Ollama/Qwen.</p>
                            </div>
                            <div class="section-head-actions">
                                <button
                                    type="button"
                                    class="icon-button checklist-toolbar-button"
                                    id="checklistDuplicateButton"
                                    aria-label="Клонувати чек-лист"
                                    title="Клонувати чек-лист"
                                >
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <rect x="9" y="9" width="10" height="10" rx="2"></rect>
                                        <path d="M15 9V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"></path>
                                    </svg>
                                </button>
                                <button
                                    type="button"
                                    class="icon-button checklist-toolbar-button"
                                    id="checklistNewButton"
                                    aria-label="Новий чек-лист"
                                    title="Новий чек-лист"
                                >
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M12 5v14"></path>
                                        <path d="M5 12h14"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="stack-list" id="checklistList">
                            @foreach($checklists as $checklist)
                                <div
                                    class="stack-item {{ $checklist['id'] === $activeChecklist['id'] ? 'is-active' : '' }}"
                                    data-checklist-id="{{ $checklist['id'] }}"
                                    role="button"
                                    tabindex="0"
                                >
                                    <div class="stack-item-title-row">
                                        <strong>{{ $checklist['name'] }}</strong>
                                    </div>
                                    <div class="stack-item-actions">
                                        <button
                                            type="button"
                                            class="icon-button stack-item-rename-button"
                                            data-checklist-rename-trigger="{{ $checklist['id'] }}"
                                            aria-label="Перейменувати чек-лист {{ $checklist['name'] }}"
                                            title="Перейменувати"
                                        >
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M12 20h9"></path>
                                                <path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4Z"></path>
                                            </svg>
                                        </button>
                                        <button
                                            type="button"
                                            class="icon-button stack-item-delete-button"
                                            data-checklist-delete-trigger="{{ $checklist['id'] }}"
                                            aria-label="Видалити чек-лист {{ $checklist['name'] }}"
                                            title="Видалити"
                                        >
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M3 6h18"></path>
                                                <path d="M8 6V4h8v2"></path>
                                                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                                                <path d="M10 11v6"></path>
                                                <path d="M14 11v6"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    <section class="table-card section-card">
                        <div class="section-head">
                            <div>
                                <div class="card-kicker">Редактор</div>
                                <h2>Створення та редагування</h2>
                                <p>Збережені пункти й бали цього чек-листа буде передано в промпт Qwen в Ollama для оцінки дзвінка.</p>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-field">
                                <label for="checklistName">Назва чек-листа</label>
                                <input class="text-input" id="checklistName" type="text" value="{{ $activeChecklist['name'] }}">
                            </div>
                            <div class="form-field">
                                <label for="checklistType">Тип сценарію</label>
                                <select class="text-select" id="checklistType">
                                    @foreach(['Перший контакт', 'Повторний дзвінок', 'Допродаж', 'Загальний сценарій'] as $checklistTypeOption)
                                        <option value="{{ $checklistTypeOption }}" @selected($activeChecklist['type'] === $checklistTypeOption)>{{ $checklistTypeOption }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-grid single" style="margin-top: 16px;">
                            <div class="form-field">
                                <label for="checklistPrompt">Промпт для оцінювання</label>
                                <textarea
                                    class="text-area"
                                    id="checklistPrompt"
                                    rows="6"
                                    placeholder="Тут можна пояснити, як саме оцінювати дзвінок: що означає якір, програмування, які нюанси вважати помилкою або навпаки правильною дією менеджера."
                                >{{ $activeChecklist['prompt'] ?? '' }}</textarea>
                            </div>
                        </div>

                        <div class="form-grid single" style="margin-top: 16px;">
                            <div class="form-field">
                                <label for="checklistItemsEditor">Пункти чек-листа</label>
                                <div class="checklist-items-board">
                                    <div class="checklist-items-head" aria-hidden="true">
                                        <span>№</span>
                                        <span>Пункт</span>
                                        <span>Балів</span>
                                        <span>+</span>
                                    </div>
                                    <div class="checklist-items-editor" id="checklistItemsEditor">
                                        @foreach(($activeChecklist['items'] ?? []) as $index => $item)
                                            <div class="checklist-item-row" data-checklist-item-row data-index="{{ $index }}">
                                                <span class="checklist-item-number" aria-hidden="true">{{ $index + 1 }}</span>
                                                <textarea
                                                    class="textarea-input checklist-item-label-input"
                                                    data-checklist-item-label
                                                    maxlength="2000"
                                                    placeholder="Назва пункту"
                                                    rows="1"
                                                >{{ $item['label'] ?? '' }}</textarea>
                                                <input
                                                    class="text-input checklist-item-points-input"
                                                    type="number"
                                                    min="1"
                                                    max="100"
                                                    value="{{ $item['max_points'] ?? 10 }}"
                                                    data-checklist-item-max-points
                                                    placeholder="10"
                                                >
                                                <button
                                                    type="button"
                                                    class="ghost-button checklist-item-add-button"
                                                    data-checklist-item-add
                                                    aria-label="Додати пункт після цього"
                                                    title="Додати пункт"
                                                >+</button>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="primary-button" id="checklistSaveButton">Зберегти чек-лист</button>
                            <button type="button" class="ghost-button" id="checklistExportButton">Скачати таблицю</button>
                        </div>

                        <input type="hidden" id="checklistId" value="{{ $activeChecklist['id'] }}">
                        <div class="transcription-feedback" id="checklistFeedback">Редактор чек-листа готовий. Його вміст буде використано при оцінюванні дзвінка.</div>
                    </section>
                </div>
            </div>
        </section>

        <section class="content-section" id="transcriptionSection">
            <div class="page transcription-page">
                <section class="transcription-shell">
                    <div class="transcription-head">
                        <h1>Транскрибація</h1>
                    </div>

                    <div class="transcription-layout">
                        <section class="transcription-panel">
                            <div class="transcription-upload-head">
                                <div class="transcription-upload-copy">
                                    <h2>Завантаження аудіо</h2>
                                    <p>Додайте запис з файла або вставте посилання на аудіо.</p>
                                </div>
                                <button
                                    type="button"
                                    class="primary-button transcription-run-icon-button"
                                    id="transcriptionRunButton"
                                    aria-label="Запустити транскрибацію"
                                    title="Запустити транскрибацію"
                                >
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M8 5v14l11-7z"></path>
                                    </svg>
                                    <span class="visually-hidden">Запустити транскрибацію</span>
                                </button>
                            </div>

                            <div class="transcription-upload-grid">
                                <label class="transcription-upload-card">
                                    <h3>Перетягніть файл або виберіть вручну</h3>
                                    <div class="file-input-wrap">
                                        <input class="transcription-file-input" id="transcriptionFileInput" type="file" accept="audio/*,.mp3,.wav,.m4a,.ogg,.webm,.mp4,.aac,.flac,.opus">
                                        <div class="transcription-file-control">
                                            <label class="transcription-file-button" for="transcriptionFileInput">Оберіть файл</label>
                                            <span class="transcription-file-name" id="transcriptionFileName">Файл не вибрано</span>
                                        </div>
                                    </div>
                                </label>

                                <div class="transcription-upload-card">
                                    <h3>Додайте посилання на аудіо</h3>
                                    <div class="file-input-wrap">
                                        <input class="text-input" id="transcriptionUrl" type="text" placeholder="https://example.com/call.mp3">
                                    </div>
                                    <p style="margin: 10px 0 0; color: #7c8fb6; font-size: 13px; line-height: 1.45;">
                                        Потрібне пряме посилання на аудіофайл. Посилання на сторінку кабінету Binotel не підійде.
                                    </p>
                                </div>
                            </div>

                            <div class="transcription-input-grid">
                                <div class="transcription-field">
                                    <label for="transcriptionTitle">Назва завдання</label>
                                    <input class="text-input" id="transcriptionTitle" type="text" value="Розбір дзвінка менеджера">
                                </div>
                                <div class="transcription-field">
                                    <label for="transcriptionLanguage">Мова аудіо</label>
                                    <select class="text-select" id="transcriptionLanguage">
                                        <option value="auto">Автовизначення</option>
                                        <option value="uk">Українська</option>
                                        <option value="ru">Російська</option>
                                        <option value="en">Англійська</option>
                                    </select>
                                </div>
                            </div>

                            <label class="transcription-toggle">
                                <input id="transcriptionEvaluate" type="checkbox" checked>
                                <span>Оцінити за чек-листом</span>
                            </label>

                            <div class="transcription-checklist-row" id="transcriptionChecklistRow">
                                <select class="text-select" id="transcriptionChecklist">
                                    @foreach($checklists as $checklist)
                                        <option value="{{ $checklist['id'] }}" @selected($checklist['id'] === $defaultChecklistId)>{{ $checklist['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="transcription-actions">
                                <div class="transcription-feedback" id="transcriptionFeedback">Готово до обробки нового аудіо.</div>
                            </div>
                        </section>

                        <section class="transcription-panel transcription-result-panel">
                            <div class="transcription-result-head">
                                <h2>Результат транскрибації</h2>
                                <div class="transcription-result-toolbar">
                                    <button
                                        type="button"
                                        class="icon-button transcription-ai-button"
                                        id="transcriptionAiRewriteButton"
                                        aria-label="AI-обробити текст"
                                        title="AI-обробити текст"
                                    >
                                        <span class="transcription-ai-button-label" aria-hidden="true">AI</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="icon-button transcription-ai-settings-button"
                                        id="transcriptionAiSettingsButton"
                                        aria-label="Налаштування AI-обробки"
                                        title="Налаштування AI-обробки"
                                    >
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <circle cx="12" cy="12" r="3"></circle>
                                            <path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1 1.55V21a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-1-1.55 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.55-1H3a2 2 0 1 1 0-4h.09a1.7 1.7 0 0 0 1.55-1 1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.55V3a2 2 0 1 1 4 0v.09a1.7 1.7 0 0 0 1 1.55 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.4 9c.2.5.75.9 1.3.98H21a2 2 0 1 1 0 4h-.3c-.55.08-1.1.48-1.3 1.02z"></path>
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        class="icon-button transcription-ai-stop-button"
                                        id="transcriptionAiStopButton"
                                        aria-label="Зупинити AI-обробку та повернути початковий текст"
                                        title="Зупинити AI-обробку та повернути початковий текст"
                                        hidden
                                    >
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M7 7h10v10H7z"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div class="transcription-result-box">
                                <textarea class="transcription-result-text" id="transcriptionResultText" placeholder="Після запуску тут з'явиться результат транскрибації."></textarea>
                            </div>

                            <div class="transcription-ai-live-box" id="transcriptionAiLiveBox" hidden>
                                <div class="transcription-score-head">
                                    <h3>Живий хід AI</h3>
                                    <div class="transcription-result-toolbar">
                                        <div class="transcription-llm-status is-pending" id="transcriptionAiLiveStatus">Очікування</div>
                                        <button
                                            type="button"
                                            class="icon-button transcription-ai-live-close-button"
                                            id="transcriptionAiLiveCloseButton"
                                            aria-label="Закрити живий хід AI"
                                            title="Закрити живий хід AI"
                                        >×</button>
                                    </div>
                                </div>

                                <div class="transcription-score-meta">
                                    <div class="transcription-score-line">
                                        <span class="transcription-score-label">Стан</span>
                                        <strong id="transcriptionAiLivePhase">Після запуску тут буде видно поточний прогрес AI-обробки та список виправлень.</strong>
                                    </div>
                                </div>

                                <pre class="transcription-llm-console transcription-ai-live-console" id="transcriptionAiLiveText">Після запуску AI тут з'явиться живий потік thinking, список виправлень і застосовані автозаміни.</pre>
                            </div>

                            <div class="transcription-ai-input-box" id="transcriptionAiInputBox" hidden>
                                <div class="transcription-score-head">
                                    <h3>Формат запиту до AI</h3>
                                    <div class="transcription-result-toolbar">
                                        <div class="transcription-llm-status is-pending" id="transcriptionAiInputStatus">Запит</div>
                                        <button
                                            type="button"
                                            class="icon-button transcription-ai-input-close-button"
                                            id="transcriptionAiInputCloseButton"
                                            aria-label="Закрити формат запиту до AI"
                                            title="Закрити формат запиту до AI"
                                        >×</button>
                                    </div>
                                </div>

                                <div class="transcription-score-meta">
                                    <div class="transcription-score-line">
                                        <span class="transcription-score-label">Формат</span>
                                        <strong id="transcriptionAiInputPhase">Після запуску тут буде видно payload і prompt, які передаються в AI.</strong>
                                    </div>
                                </div>

                                <pre class="transcription-llm-console transcription-ai-input-console" id="transcriptionAiInputText">Після запуску AI тут з'явиться формат запиту.</pre>
                            </div>

                            <div class="transcription-llm-box" id="transcriptionLlmBox">
                                <div class="transcription-score-head">
                                    <h3>Хід роботи LLM</h3>
                                    <div class="transcription-result-toolbar">
                                        <div class="transcription-llm-status is-pending" id="transcriptionLlmStatus">Очікування</div>
                                        <button
                                            type="button"
                                            class="icon-button transcription-ai-button"
                                            id="transcriptionLlmEvaluateButton"
                                            aria-label="Запустити LLM-оцінювання"
                                            title="Запустити LLM-оцінювання"
                                        >
                                            <span class="transcription-ai-button-label" aria-hidden="true">AI</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="icon-button transcription-ai-settings-button"
                                            id="transcriptionLlmSettingsButton"
                                            aria-label="Налаштування LLM-оцінювання"
                                            title="Налаштування LLM-оцінювання"
                                        >
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <circle cx="12" cy="12" r="3"></circle>
                                                <path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1 1.55V21a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-1-1.55 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.55-1H3a2 2 0 1 1 0-4h.09a1.7 1.7 0 0 0 1.55-1 1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.55V3a2 2 0 1 1 4 0v.09a1.7 1.7 0 0 0 1 1.55 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.4 9c.2.5.75.9 1.3.98H21a2 2 0 1 1 0 4h-.3c-.55.08-1.1.48-1.3 1.02z"></path>
                                            </svg>
                                        </button>
                                        <button
                                            type="button"
                                            class="icon-button transcription-ai-stop-button"
                                            id="transcriptionStopButton"
                                            aria-label="Зупинити та скинути поточну задачу"
                                            title="Зупинити та скинути поточну задачу"
                                            hidden
                                            disabled
                                        >
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M7 7h10v10H7z"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <div class="transcription-score-meta">
                                    <div class="transcription-score-line">
                                        <span class="transcription-score-label">Етап</span>
                                        <strong id="transcriptionLlmPhase">Після запуску тут буде видно етапи роботи Qwen / Ollama.</strong>
                                    </div>
                                </div>

                                <pre class="transcription-llm-console" id="transcriptionLlmConsole">Поки що LLM ще не запускалась.</pre>

                                <button
                                    type="button"
                                    class="ghost-button transcription-llm-toggle"
                                    id="transcriptionLlmPromptToggle"
                                    aria-expanded="false"
                                    aria-controls="transcriptionLlmPromptDetails"
                                >
                                    Показати prompt для Qwen
                                </button>

                                <div class="transcription-llm-prompt-details" id="transcriptionLlmPromptDetails" hidden>
                                    <pre class="transcription-llm-prompt-text" id="transcriptionLlmPromptText">Після запуску тут з'явиться точний runtime-prompt, який backend відправив у Qwen / Ollama.</pre>
                                </div>
                            </div>

                            <div class="transcription-score-box" id="transcriptionScoreBox">
                                <div class="transcription-score-head">
                                    <h3>Оцінка</h3>
                                    <div class="score-chip score-mid" id="transcriptionScoreValue">--</div>
                                </div>

                                <div class="transcription-score-meta">
                                    <div class="transcription-score-line">
                                        <span class="transcription-score-label">Чек-лист</span>
                                        <strong id="transcriptionScoreChecklistName">Оцінювання буде після запуску</strong>
                                    </div>
                                    <div class="transcription-score-line">
                                        <span class="transcription-score-label">Сильна сторона</span>
                                        <strong id="transcriptionScoreStrongSide">—</strong>
                                    </div>
                                    <div class="transcription-score-line">
                                        <span class="transcription-score-label">Фокус</span>
                                        <strong id="transcriptionScoreFocus">—</strong>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    class="ghost-button transcription-score-toggle"
                                    id="transcriptionScoreDetailsToggle"
                                    aria-expanded="false"
                                    aria-controls="transcriptionScoreDetails"
                                    hidden
                                >
                                    Показати оцінки по пунктах
                                </button>

                                <div class="transcription-score-details" id="transcriptionScoreDetails" hidden>
                                    <div class="transcription-score-items" id="transcriptionScoreItems"></div>
                                </div>
                            </div>
                        </section>
                    </div>
                </section>
            </div>
        </section>

        <section class="content-section" id="settingsSection">
            <div class="page">
                <section class="table-card section-card">
                    <div class="section-head">
                        <div>
                            <div class="card-kicker">Налаштування</div>
                            <h2>Доступ і транскрибація</h2>
                            <p>Тут залишені доступ до Ollama/API, модель faster-whisper і коректне визначення автора реплік. LLM-модель і параметри тепер налаштовуються окремо біля кожного AI-блоку.</p>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-field">
                            <label for="settingsLogin">Логін</label>
                            <input class="text-input" id="settingsLogin" type="text" value="manager.qa">
                        </div>
                        <div class="form-field">
                            <label for="settingsPassword">Новий пароль</label>
                            <input class="text-input" id="settingsPassword" type="password" value="password">
                        </div>
                        <div class="form-field">
                            <label for="settingsApiUrl">API URL</label>
                            <input class="text-input" id="settingsApiUrl" type="text" value="{{ $transcriptionSettings['llm_api_url'] ?? 'http://llm_yaprofi_ollama:11434' }}">
                        </div>
                        <div class="form-field">
                            <label for="settingsApiKey">API Key / Bearer token</label>
                            <input
                                class="text-input"
                                id="settingsApiKey"
                                type="password"
                                value=""
                                placeholder="{{ ($transcriptionSettings['llm_has_api_key'] ?? false) ? 'Ключ уже збережено. Введіть новий тільки якщо хочете замінити.' : 'Необовʼязково для локального Ollama' }}"
                            >
                        </div>
                        <div class="form-field">
                            <label for="settingsWhisperModel">Модель faster-whisper</label>
                            <select class="text-select" id="settingsWhisperModel">
                                @foreach(($transcriptionSettings['available_models'] ?? []) as $model)
                                    <option value="{{ $model }}" @selected(($transcriptionSettings['transcription_model'] ?? null) === $model)>{{ $model }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-field" style="grid-column: 1 / -1;">
                            <label for="settingsWhisperInitialPrompt">Initial prompt faster-whisper</label>
                            <textarea
                                class="textarea-input"
                                id="settingsWhisperInitialPrompt"
                                maxlength="4000"
                                rows="4"
                                placeholder="Наприклад: У розмові можуть бути назви товарів, артикулі, Binotel, LiqPay, Viber..."
                            >{{ $transcriptionSettings['transcription_initial_prompt'] ?? '' }}</textarea>
                            <p style="margin: 10px 0 0; color: #7c8fb6; font-size: 13px; line-height: 1.45;">
                                Підказка передається напряму у faster-whisper як initial_prompt і допомагає моделі краще розпізнавати назви, артикули та специфічні слова.
                            </p>
                        </div>
                        <div class="form-field">
                            <label for="settingsSpeakerDiarizationEnabled">Визначення автора реплік</label>
                            <label class="transcription-toggle" style="margin-top: 12px;">
                                <input id="settingsSpeakerDiarizationEnabled" type="checkbox" @checked($transcriptionSettings['speaker_diarization_enabled'] ?? false)>
                                <span>Увімкнути speaker diarization через pyannote</span>
                            </label>
                            <p style="margin: 10px 0 0; color: #7c8fb6; font-size: 13px; line-height: 1.45;">
                                Для коректного розділення менеджера і клієнта потрібен Hugging Face token з доступом до {{ $transcriptionSettings['speaker_diarization_provider_model'] ?? 'pyannote/speaker-diarization-community-1' }}.
                            </p>
                        </div>
                        <div class="form-field">
                            <label for="settingsSpeakerDiarizationToken">Hugging Face token</label>
                            <input
                                class="text-input"
                                id="settingsSpeakerDiarizationToken"
                                type="password"
                                value=""
                                placeholder="{{ ($transcriptionSettings['speaker_diarization_has_token'] ?? false) ? 'Токен уже збережено. Введіть новий тільки якщо хочете замінити.' : 'hf_xxx' }}"
                            >
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="primary-button" id="settingsSaveButton">Зберегти налаштування</button>
                        <button type="button" class="ghost-button" id="settingsCheckConnectionButton">Перевірити підключення</button>
                        <div class="settings-feedback is-success" id="settingsFeedback">
                            Ollama/API: {{ $transcriptionSettings['llm_api_url'] ?? 'http://llm_yaprofi_ollama:11434' }}. faster-whisper: {{ $transcriptionSettings['transcription_model'] ?? 'large-v3' }}. Автор реплік: {{ ($transcriptionSettings['speaker_diarization_enabled'] ?? false) ? 'увімкнено' : 'вимкнено' }}. {{ ($transcriptionSettings['speaker_diarization_has_token'] ?? false) ? 'Hugging Face token збережено.' : 'Додайте Hugging Face token, щоб pyannote коректно розділяв менеджера і клієнта.' }}
                        </div>
                    </div>
                </section>
            </div>
        </section>
    </main>
</div>

<div class="modal-overlay" id="callModal" hidden>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-header">
            <div>
                <div class="modal-kicker" id="modalKicker">Перегляд</div>
                <h2 class="modal-title" id="modalTitle">Деталі дзвінка</h2>
                <p class="modal-subtitle" id="modalSubtitle">Подробиці вибраного дзвінка.</p>
            </div>
            <button type="button" class="modal-close" id="modalClose" aria-label="Закрити">×</button>
        </div>
        <div class="modal-body" id="modalBody"></div>
    </div>
</div>

<div class="modal-overlay" id="checklistDeleteModal" hidden>
    <div class="modal-card confirm-modal-card" role="dialog" aria-modal="true" aria-labelledby="checklistDeleteTitle">
        <div class="modal-header">
            <div>
                <div class="modal-kicker">Видалення</div>
                <h2 class="modal-title" id="checklistDeleteTitle">Видалення чек-листа</h2>
                <p class="modal-subtitle">Підтвердьте дію перед видаленням.</p>
            </div>
            <button type="button" class="modal-close" id="checklistDeleteClose" aria-label="Закрити">×</button>
        </div>
        <div class="modal-body confirm-modal-body">
            <p class="confirm-modal-message" id="checklistDeleteMessage">Ви впевнені, що хочете видалити чек-лист?</p>
            <div class="confirm-modal-actions">
                <button type="button" class="ghost-button" id="checklistDeleteConfirmButton">Так</button>
                <button type="button" class="primary-button" id="checklistDeleteCancelButton">Ні</button>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="checklistExportModal" hidden>
    <div class="modal-card checklist-export-modal-card" role="dialog" aria-modal="true" aria-labelledby="checklistExportTitle">
        <div class="modal-header">
            <div>
                <div class="modal-kicker">Експорт</div>
                <h2 class="modal-title" id="checklistExportTitle">Скачати таблицю</h2>
                <p class="modal-subtitle">Оберіть формат для поточного чек-листа.</p>
            </div>
            <button type="button" class="modal-close" id="checklistExportClose" aria-label="Закрити">×</button>
        </div>
        <div class="modal-body checklist-export-modal-body">
            <div class="checklist-export-actions">
                <button type="button" class="primary-button" id="checklistExportChatGptButton">Скачати таблицю для ChatGPT</button>
                <button type="button" class="ghost-button" id="checklistExportExcelButton">Скачати в Excel</button>
                <button type="button" class="ghost-button" id="checklistExportGoogleButton">Скачати для Google Sheets</button>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="transcriptionLlmSettingsModal" hidden>
    <div class="modal-card transcription-ai-modal-card" role="dialog" aria-modal="true" aria-labelledby="transcriptionLlmSettingsTitle">
        <div class="modal-header">
            <div>
                <div class="modal-kicker">LLM</div>
                <h2 class="modal-title" id="transcriptionLlmSettingsTitle">Налаштування оцінювання</h2>
                <p class="modal-subtitle">Ці параметри застосовуються тільки до блоку “Хід роботи LLM” і не змінюють інші AI-блоки.</p>
            </div>
            <button type="button" class="modal-close" id="transcriptionLlmSettingsClose" aria-label="Закрити">×</button>
        </div>
        <div class="modal-body">
            <div class="transcription-ai-form">
                <div class="form-field">
                    <label for="transcriptionLlmModel">LLM модель</label>
                    <select class="text-select" id="transcriptionLlmModel"></select>
                </div>
                <div class="form-field">
                    <label for="transcriptionLlmSystemPrompt">Системний prompt</label>
                    <textarea
                        class="textarea-input transcription-llm-system-prompt"
                        id="transcriptionLlmSystemPrompt"
                        maxlength="20000"
                    ></textarea>
                </div>
                <div class="transcription-ai-local-settings">
                    <div class="transcription-ai-local-settings-head">
                        <h3>Параметри цієї LLM-оцінки</h3>
                        <p>Модель, температура та інші параметри збережуться тільки для цього блоку оцінювання чек-листа.</p>
                        <button type="button" class="ghost-button" id="transcriptionLlmResetModelSettingsButton">Скинути поточну модель до дефолту</button>
                    </div>
                    <div class="form-field">
                        <label for="transcriptionLlmThinkingEnabled">Thinking режим</label>
                        <label class="transcription-toggle" style="margin-top: 4px;">
                            <input id="transcriptionLlmThinkingEnabled" type="checkbox">
                            <span>Thinking вимкнено для всіх моделей</span>
                        </label>
                    </div>
                    <div class="form-field">
                        <label for="transcriptionLlmTemperature">Температура</label>
                        <div class="range-setting">
                            <div class="range-setting-head">
                                <span class="range-setting-value" id="transcriptionLlmTemperatureValue">0,2</span>
                            </div>
                            <input class="settings-range-input" id="transcriptionLlmTemperature" type="range" min="0" max="2" step="0.1">
                            <div class="range-setting-limits">
                                <span>0,0</span>
                                <span>2,0</span>
                            </div>
                        </div>
                    </div>
                    <div class="form-field">
                        <label for="transcriptionLlmNumCtx">Контекстне вікно</label>
                        <div class="range-setting">
                            <div class="range-setting-head">
                                <span class="range-setting-value" id="transcriptionLlmNumCtxValue">4 096</span>
                            </div>
                            <input class="settings-range-input" id="transcriptionLlmNumCtx" type="range" min="256" max="131072" step="256">
                            <div class="range-setting-limits">
                                <span>256</span>
                                <span>131 072</span>
                            </div>
                        </div>
                    </div>
                    <div class="transcription-ai-compact-grid">
                        <div class="form-field">
                            <label for="transcriptionLlmTopK">Top K</label>
                            <input class="text-input" id="transcriptionLlmTopK" type="number" min="1" max="500" step="1">
                        </div>
                        <div class="form-field">
                            <label for="transcriptionLlmTopP">Top P</label>
                            <input class="text-input" id="transcriptionLlmTopP" type="number" min="0" max="1" step="0.01">
                        </div>
                        <div class="form-field">
                            <label for="transcriptionLlmRepeatPenalty">repeat_penalty</label>
                            <input class="text-input" id="transcriptionLlmRepeatPenalty" type="number" min="0" max="5" step="0.05">
                        </div>
                        <div class="form-field">
                            <label for="transcriptionLlmNumPredict">num_predict</label>
                            <input class="text-input" id="transcriptionLlmNumPredict" type="number" min="-1" max="32768" step="1">
                        </div>
                        <div class="form-field">
                            <label for="transcriptionLlmSeed">Seed</label>
                            <input class="text-input" id="transcriptionLlmSeed" type="number" step="1" placeholder="Порожньо = випадково">
                        </div>
                        <div class="form-field">
                            <label for="transcriptionLlmTimeoutSeconds">Timeout LLM (сек)</label>
                            <input class="text-input" id="transcriptionLlmTimeoutSeconds" type="number" min="15" max="3600" step="1">
                        </div>
                    </div>
                </div>
                <p class="transcription-ai-help">Кнопка AI в блоці “Хід роботи LLM” запускатиме оцінювання з цими локальними параметрами.</p>
                <div class="confirm-modal-actions">
                    <button type="button" class="ghost-button" id="transcriptionLlmSettingsCancelButton">Скасувати</button>
                    <button type="button" class="primary-button" id="transcriptionLlmSettingsSaveButton">Зберегти</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="transcriptionAiSettingsModal" hidden>
    <div class="modal-card transcription-ai-modal-card" role="dialog" aria-modal="true" aria-labelledby="transcriptionAiSettingsTitle">
        <div class="modal-header">
            <div>
                <div class="modal-kicker">AI</div>
                <h2 class="modal-title" id="transcriptionAiSettingsTitle">Налаштування AI-обробки</h2>
                <p class="modal-subtitle">Оберіть модель і задайте промт, який буде застосовано до тексту в полі результату транскрибації.</p>
            </div>
            <button type="button" class="modal-close" id="transcriptionAiSettingsClose" aria-label="Закрити">×</button>
        </div>
        <div class="modal-body">
            <div class="transcription-ai-form">
                <div class="form-field">
                    <label for="transcriptionAiModel">AI модель</label>
                    <select class="text-select" id="transcriptionAiModel"></select>
                </div>
                <div class="form-field">
                    <label for="transcriptionAiPrompt">Промт</label>
                    <textarea
                        class="textarea-input transcription-ai-prompt"
                        id="transcriptionAiPrompt"
                        maxlength="4000"
                        placeholder="Наприклад: знайди тільки очевидні орфографічні помилки, не змінюй сенс і структуру реплік."
                    ></textarea>
                </div>
                <div class="transcription-ai-local-settings">
                    <div class="transcription-ai-local-settings-head">
                        <h3>Параметри цієї AI-кнопки</h3>
                        <p>Ці значення застосовуються тільки до AI-обробки цього поля транскрибації і не змінюють загальні налаштування Ollama.</p>
                        <button type="button" class="ghost-button" id="transcriptionAiResetModelSettingsButton">Скинути поточну модель до дефолту</button>
                    </div>
                    <div class="form-field">
                        <label for="transcriptionAiThinkingEnabled">Thinking режим</label>
                        <label class="transcription-toggle" style="margin-top: 4px;">
                            <input id="transcriptionAiThinkingEnabled" type="checkbox">
                            <span>Thinking вимкнено для всіх моделей</span>
                        </label>
                    </div>
                    <div class="form-field">
                        <label for="transcriptionAiTemperature">Температура</label>
                        <div class="range-setting">
                            <div class="range-setting-head">
                                <span class="range-setting-value" id="transcriptionAiTemperatureValue">0,2</span>
                            </div>
                            <input
                                class="settings-range-input"
                                id="transcriptionAiTemperature"
                                type="range"
                                min="0"
                                max="2"
                                step="0.1"
                            >
                            <div class="range-setting-limits">
                                <span>0,0</span>
                                <span>2,0</span>
                            </div>
                        </div>
                    </div>
                    <div class="form-field">
                        <label for="transcriptionAiNumCtx">Контекстне вікно</label>
                        <div class="range-setting">
                            <div class="range-setting-head">
                                <span class="range-setting-value" id="transcriptionAiNumCtxValue">4 096</span>
                            </div>
                            <input
                                class="settings-range-input"
                                id="transcriptionAiNumCtx"
                                type="range"
                                min="256"
                                max="131072"
                                step="256"
                            >
                            <div class="range-setting-limits">
                                <span>256</span>
                                <span>131 072</span>
                            </div>
                        </div>
                    </div>
                    <div class="transcription-ai-compact-grid">
                        <div class="form-field">
                            <label for="transcriptionAiTopK">Top K</label>
                            <input class="text-input" id="transcriptionAiTopK" type="number" min="1" max="500" step="1">
                        </div>
                        <div class="form-field">
                            <label for="transcriptionAiTopP">Top P</label>
                            <input class="text-input" id="transcriptionAiTopP" type="number" min="0" max="1" step="0.01">
                        </div>
                        <div class="form-field">
                            <label for="transcriptionAiRepeatPenalty">repeat_penalty</label>
                            <input class="text-input" id="transcriptionAiRepeatPenalty" type="number" min="0" max="5" step="0.05">
                        </div>
                        <div class="form-field">
                            <label for="transcriptionAiNumPredict">num_predict</label>
                            <input class="text-input" id="transcriptionAiNumPredict" type="number" min="-1" max="32768" step="1">
                        </div>
                        <div class="form-field">
                            <label for="transcriptionAiSeed">Seed</label>
                            <input class="text-input" id="transcriptionAiSeed" type="number" step="1" placeholder="Порожньо = випадково">
                        </div>
                        <div class="form-field">
                            <label for="transcriptionAiTimeoutSeconds">Timeout LLM (сек)</label>
                            <input class="text-input" id="transcriptionAiTimeoutSeconds" type="number" min="15" max="3600" step="1">
                        </div>
                    </div>
                </div>
                <p class="transcription-ai-help">Після запуску AI поверне список виправлень, а скрипт застосує тільки точні збіги в тексті.</p>
                <div class="confirm-modal-actions">
                    <button type="button" class="ghost-button" id="transcriptionAiSettingsCancelButton">Скасувати</button>
                    <button type="button" class="primary-button" id="transcriptionAiSettingsSaveButton">Зберегти</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let calls = @json($calls);
    const initialChecklists = @json($checklists);
    let defaultChecklistId = @json($defaultChecklistId);
    const checklistsEndpoint = @json($checklistsEndpoint);
    const transcriptionEndpoint = @json($transcriptionEndpoint);
    const transcriptionTaskEndpoint = @json($transcriptionTaskEndpoint);
    const transcriptionAiRewriteEndpoint = @json($transcriptionAiRewriteEndpoint);
    const transcriptionEvaluationEndpoint = @json($transcriptionEvaluationEndpoint);
    let transcriptionServerUploadLimitBytes = @json($transcriptionUploadLimitBytes);
    const transcriptionSettings = @json($transcriptionSettings);
    const transcriptionSettingsEndpoint = @json($transcriptionSettingsEndpoint);
    const pageBootstrapEndpoint = @json($pageBootstrapEndpoint);
    const callAudioEndpoint = @json($callAudioEndpoint ?? '');
    const defaultTranscriptionLlmSystemPrompt = @json($transcriptionLlmSystemPrompt ?? '');
    const initialActiveEvaluationJob = @json($activeEvaluationJob ?? null);
    const transcriptionLanguageLabels = {
        auto: "Автовизначення",
        uk: "Українська",
        ru: "Російська",
        en: "Англійська"
    };

    function normalizeManagerName(value) {
        return String(value)
            .replace(/^Wire:\s*/i, "")
            .replace(/\s+Sip$/i, "")
            .trim();
    }

    function buildManagerRecommendation(score, callsCount) {
        if (score === null) {
            return `На ${callsCount} дзвінках ще немає AI-оцінки. Дані вже в системі, оцінка з'явиться після аналізу.`;
        }

        if (score >= 90) {
            return `Сильний результат на ${callsCount} дзвінках. Можна використовувати як внутрішній орієнтир для команди.`;
        }

        if (score >= 85) {
            return `Стабільна якість на ${callsCount} дзвінках. Добре працює структура, варто ще посилити завершення розмови.`;
        }

        return `Потрібна додаткова увага на ${callsCount} дзвінках. Рекомендуємо посилити виявлення потреби, заперечення та фіксацію наступного кроку.`;
    }

    function buildManagersFromCalls(items) {
        const groups = new Map();

        items.forEach((call) => {
            const key = normalizeManagerName(call.employee);
            const existing = groups.get(key) || {
                name: key,
                totalScore: 0,
                callsCount: 0,
                scoredCallsCount: 0
            };

            const score = numericScoreValue(call.score);

            if (score !== null) {
                existing.totalScore += score;
                existing.scoredCallsCount += 1;
            }

            existing.callsCount += 1;
            groups.set(key, existing);
        });

        return [...groups.values()]
            .map((item) => {
                const score = item.scoredCallsCount > 0
                    ? Math.round(item.totalScore / item.scoredCallsCount)
                    : null;

                return {
                    name: item.name,
                    callsCount: item.callsCount,
                    score,
                    recommendation: buildManagerRecommendation(score, item.callsCount)
                };
            })
            .sort((left, right) => {
                if (right.score !== left.score) {
                    return right.score - left.score;
                }

                return right.callsCount - left.callsCount;
            });
    }

    const tableBody = document.getElementById("callsTableBody");
    const callsPagination = document.getElementById("callsPagination");
    const callsCount = document.getElementById("callsCount");
    const activeDateLabel = document.getElementById("activeDateLabel");
    const managersDateRangeText = document.getElementById("managersDateRangeText");
    const phoneSearch = document.getElementById("phoneSearch");
    const employeeFilterField = document.getElementById("employeeFilterField");
    const employeeFilter = document.getElementById("employeeFilter");
    const employeeFilterTrigger = document.getElementById("employeeFilterTrigger");
    const employeeFilterText = document.getElementById("employeeFilterText");
    const employeeFilterDropdown = document.getElementById("employeeFilterDropdown");
    const employeeFilterBackdrop = document.getElementById("employeeFilterBackdrop");
    const managersTableBody = document.getElementById("managersTableBody");
    const managersPagination = document.getElementById("managersPagination");
    const dateRangeTrigger = document.getElementById("dateRangeTrigger");
    const dateRangeText = document.getElementById("dateRangeText");
    const datePicker = document.getElementById("datePicker");
    const monthTitleA = document.getElementById("monthTitleA");
    const monthTitleB = document.getElementById("monthTitleB");
    const weekdaysA = document.getElementById("weekdaysA");
    const weekdaysB = document.getElementById("weekdaysB");
    const monthGridA = document.getElementById("monthGridA");
    const monthGridB = document.getElementById("monthGridB");
    const calendarPrev = document.getElementById("calendarPrev");
    const calendarNext = document.getElementById("calendarNext");
    const dateStartInput = document.getElementById("dateStartInput");
    const dateEndInput = document.getElementById("dateEndInput");
    const dateCancel = document.getElementById("dateCancel");
    const dateApply = document.getElementById("dateApply");
    const interactionCountSort = document.getElementById("interactionCountSort");
    const interactionNumberSort = document.getElementById("interactionNumberSort");
    const modelSort = document.getElementById("modelSort");
    const durationSort = document.getElementById("durationSort");
    const timeSort = document.getElementById("timeSort");
    const scoreSort = document.getElementById("scoreSort");
    const callsTableWrap = document.getElementById("callsTableWrap");
    const callsTable = document.getElementById("callsTable");
    const callModal = document.getElementById("callModal");
    const modalKicker = document.getElementById("modalKicker");
    const modalTitle = document.getElementById("modalTitle");
    const modalSubtitle = document.getElementById("modalSubtitle");
    const modalBody = document.getElementById("modalBody");
    const modalClose = document.getElementById("modalClose");
    const checklistDeleteModal = document.getElementById("checklistDeleteModal");
    const checklistDeleteClose = document.getElementById("checklistDeleteClose");
    const checklistDeleteMessage = document.getElementById("checklistDeleteMessage");
    const checklistDeleteConfirmButton = document.getElementById("checklistDeleteConfirmButton");
    const checklistDeleteCancelButton = document.getElementById("checklistDeleteCancelButton");
    const checklistExportModal = document.getElementById("checklistExportModal");
    const checklistExportClose = document.getElementById("checklistExportClose");
    const checklistExportChatGptButton = document.getElementById("checklistExportChatGptButton");
    const checklistExportExcelButton = document.getElementById("checklistExportExcelButton");
    const checklistExportGoogleButton = document.getElementById("checklistExportGoogleButton");
    const transcriptionLlmSettingsModal = document.getElementById("transcriptionLlmSettingsModal");
    const transcriptionLlmSettingsClose = document.getElementById("transcriptionLlmSettingsClose");
    const transcriptionLlmSettingsCancelButton = document.getElementById("transcriptionLlmSettingsCancelButton");
    const transcriptionLlmSettingsSaveButton = document.getElementById("transcriptionLlmSettingsSaveButton");
    const transcriptionLlmResetModelSettingsButton = document.getElementById("transcriptionLlmResetModelSettingsButton");
    const transcriptionAiSettingsModal = document.getElementById("transcriptionAiSettingsModal");
    const transcriptionAiSettingsClose = document.getElementById("transcriptionAiSettingsClose");
    const transcriptionAiSettingsCancelButton = document.getElementById("transcriptionAiSettingsCancelButton");
    const transcriptionAiSettingsSaveButton = document.getElementById("transcriptionAiSettingsSaveButton");
    const transcriptionAiResetModelSettingsButton = document.getElementById("transcriptionAiResetModelSettingsButton");
    const transcriptionFileInput = document.getElementById("transcriptionFileInput");
    const transcriptionFileName = document.getElementById("transcriptionFileName");
    const transcriptionUrl = document.getElementById("transcriptionUrl");
    const transcriptionTitle = document.getElementById("transcriptionTitle");
    const transcriptionLanguage = document.getElementById("transcriptionLanguage");
    const transcriptionEvaluate = document.getElementById("transcriptionEvaluate");
    const transcriptionChecklistRow = document.getElementById("transcriptionChecklistRow");
    const transcriptionChecklist = document.getElementById("transcriptionChecklist");
    const transcriptionRunButton = document.getElementById("transcriptionRunButton");
    const transcriptionStopButton = document.getElementById("transcriptionStopButton");
    const transcriptionFeedback = document.getElementById("transcriptionFeedback");
    const transcriptionLlmEvaluateButton = document.getElementById("transcriptionLlmEvaluateButton");
    const transcriptionLlmSettingsButton = document.getElementById("transcriptionLlmSettingsButton");
    const transcriptionLlmModel = document.getElementById("transcriptionLlmModel");
    const transcriptionLlmSystemPrompt = document.getElementById("transcriptionLlmSystemPrompt");
    const transcriptionLlmThinkingEnabled = document.getElementById("transcriptionLlmThinkingEnabled");
    const transcriptionLlmTemperature = document.getElementById("transcriptionLlmTemperature");
    const transcriptionLlmTemperatureValue = document.getElementById("transcriptionLlmTemperatureValue");
    const transcriptionLlmNumCtx = document.getElementById("transcriptionLlmNumCtx");
    const transcriptionLlmNumCtxValue = document.getElementById("transcriptionLlmNumCtxValue");
    const transcriptionLlmTopK = document.getElementById("transcriptionLlmTopK");
    const transcriptionLlmTopP = document.getElementById("transcriptionLlmTopP");
    const transcriptionLlmRepeatPenalty = document.getElementById("transcriptionLlmRepeatPenalty");
    const transcriptionLlmNumPredict = document.getElementById("transcriptionLlmNumPredict");
    const transcriptionLlmSeed = document.getElementById("transcriptionLlmSeed");
    const transcriptionLlmTimeoutSeconds = document.getElementById("transcriptionLlmTimeoutSeconds");
    const transcriptionAiRewriteButton = document.getElementById("transcriptionAiRewriteButton");
    const transcriptionAiSettingsButton = document.getElementById("transcriptionAiSettingsButton");
    const transcriptionAiStopButton = document.getElementById("transcriptionAiStopButton");
    const transcriptionAiModel = document.getElementById("transcriptionAiModel");
    const transcriptionAiPrompt = document.getElementById("transcriptionAiPrompt");
    const transcriptionAiThinkingEnabled = document.getElementById("transcriptionAiThinkingEnabled");
    const transcriptionAiTemperature = document.getElementById("transcriptionAiTemperature");
    const transcriptionAiTemperatureValue = document.getElementById("transcriptionAiTemperatureValue");
    const transcriptionAiNumCtx = document.getElementById("transcriptionAiNumCtx");
    const transcriptionAiNumCtxValue = document.getElementById("transcriptionAiNumCtxValue");
    const transcriptionAiTopK = document.getElementById("transcriptionAiTopK");
    const transcriptionAiTopP = document.getElementById("transcriptionAiTopP");
    const transcriptionAiRepeatPenalty = document.getElementById("transcriptionAiRepeatPenalty");
    const transcriptionAiNumPredict = document.getElementById("transcriptionAiNumPredict");
    const transcriptionAiSeed = document.getElementById("transcriptionAiSeed");
    const transcriptionAiTimeoutSeconds = document.getElementById("transcriptionAiTimeoutSeconds");
    const transcriptionResultText = document.getElementById("transcriptionResultText");
    const transcriptionAiLiveBox = document.getElementById("transcriptionAiLiveBox");
    const transcriptionAiLiveCloseButton = document.getElementById("transcriptionAiLiveCloseButton");
    const transcriptionAiLiveStatus = document.getElementById("transcriptionAiLiveStatus");
    const transcriptionAiLivePhase = document.getElementById("transcriptionAiLivePhase");
    const transcriptionAiLiveText = document.getElementById("transcriptionAiLiveText");
    const transcriptionAiInputBox = document.getElementById("transcriptionAiInputBox");
    const transcriptionAiInputCloseButton = document.getElementById("transcriptionAiInputCloseButton");
    const transcriptionAiInputStatus = document.getElementById("transcriptionAiInputStatus");
    const transcriptionAiInputPhase = document.getElementById("transcriptionAiInputPhase");
    const transcriptionAiInputText = document.getElementById("transcriptionAiInputText");
    const transcriptionLlmStatus = document.getElementById("transcriptionLlmStatus");
    const transcriptionLlmPhase = document.getElementById("transcriptionLlmPhase");
    const transcriptionLlmConsole = document.getElementById("transcriptionLlmConsole");
    const transcriptionLlmPromptToggle = document.getElementById("transcriptionLlmPromptToggle");
    const transcriptionLlmPromptDetails = document.getElementById("transcriptionLlmPromptDetails");
    const transcriptionLlmPromptText = document.getElementById("transcriptionLlmPromptText");
    const transcriptionScoreValue = document.getElementById("transcriptionScoreValue");
    const transcriptionScoreChecklistName = document.getElementById("transcriptionScoreChecklistName");
    const transcriptionScoreStrongSide = document.getElementById("transcriptionScoreStrongSide");
    const transcriptionScoreFocus = document.getElementById("transcriptionScoreFocus");
    const transcriptionScoreDetailsToggle = document.getElementById("transcriptionScoreDetailsToggle");
    const transcriptionScoreDetails = document.getElementById("transcriptionScoreDetails");
    const transcriptionScoreItems = document.getElementById("transcriptionScoreItems");
    const checklistList = document.getElementById("checklistList");
    const checklistIdField = document.getElementById("checklistId");
    const checklistName = document.getElementById("checklistName");
    const checklistType = document.getElementById("checklistType");
    const checklistItemsEditor = document.getElementById("checklistItemsEditor");
    const checklistNewButton = document.getElementById("checklistNewButton");
    const checklistSaveButton = document.getElementById("checklistSaveButton");
    const checklistDuplicateButton = document.getElementById("checklistDuplicateButton");
    const checklistExportButton = document.getElementById("checklistExportButton");
    const checklistFeedback = document.getElementById("checklistFeedback");
    const checklistPrompt = document.getElementById("checklistPrompt");
    const settingsLogin = document.getElementById("settingsLogin");
    const settingsPassword = document.getElementById("settingsPassword");
    const settingsApiUrl = document.getElementById("settingsApiUrl");
    const settingsApiKey = document.getElementById("settingsApiKey");
    const settingsProvider = document.getElementById("settingsProvider");
    const settingsModel = document.getElementById("settingsModel");
    const settingsLlmThinkingEnabled = document.getElementById("settingsLlmThinkingEnabled");
    const settingsLlmTemperature = document.getElementById("settingsLlmTemperature");
    const settingsLlmTemperatureValue = document.getElementById("settingsLlmTemperatureValue");
    const settingsLlmNumCtx = document.getElementById("settingsLlmNumCtx");
    const settingsLlmNumCtxValue = document.getElementById("settingsLlmNumCtxValue");
    const settingsLlmTopK = document.getElementById("settingsLlmTopK");
    const settingsLlmTopP = document.getElementById("settingsLlmTopP");
    const settingsLlmRepeatPenalty = document.getElementById("settingsLlmRepeatPenalty");
    const settingsLlmNumPredict = document.getElementById("settingsLlmNumPredict");
    const settingsLlmSeed = document.getElementById("settingsLlmSeed");
    const settingsLlmTimeoutSeconds = document.getElementById("settingsLlmTimeoutSeconds");
    const settingsWhisperModel = document.getElementById("settingsWhisperModel");
    const settingsWhisperInitialPrompt = document.getElementById("settingsWhisperInitialPrompt");
    const settingsSpeakerDiarizationEnabled = document.getElementById("settingsSpeakerDiarizationEnabled");
    const settingsSpeakerDiarizationToken = document.getElementById("settingsSpeakerDiarizationToken");
    const settingsSaveButton = document.getElementById("settingsSaveButton");
    const settingsCheckConnectionButton = document.getElementById("settingsCheckConnectionButton");
    const settingsFeedback = document.getElementById("settingsFeedback");
    const navItems = [...document.querySelectorAll(".nav-item")];
    const contentSections = [...document.querySelectorAll(".content-section")];
    calls = Array.isArray(calls) ? calls : [];
    const interactionHistoryRequest = readInteractionHistoryRequest();
    const isInteractionHistoryMode = interactionHistoryRequest !== null;
    if (isInteractionHistoryMode) {
        calls = filterInteractionHistoryRequestCalls(interactionHistoryRequest, calls);
    }
    const defaultTranscriptionButtonLabel = transcriptionRunButton?.textContent?.trim() || "Запустити транскрибацію";
    const defaultStopButtonLabel = transcriptionStopButton?.getAttribute("aria-label") || transcriptionStopButton?.textContent?.trim() || "Зупинити та скинути";
    const defaultChecklistFeedback = checklistFeedback?.textContent?.trim() || "Редактор чек-листа готовий.";
    const forcedTranscriptionRequest = readForcedTranscriptionRequest();
    const checklistSelectionStorageKey = `call-center.active-checklist:${window.location.pathname}`;
    const callsTableColumnStorageKey = `call-center.calls-table-columns:${window.location.pathname}`;
    const callsTableColumnConfig = [
        { id: "direction", minWidth: 42 },
        { id: "interactionCount", minWidth: 86 },
        { id: "interactionNumber", minWidth: 104 },
        { id: "caller", minWidth: 130 },
        { id: "model", minWidth: 148 },
        { id: "employee", minWidth: 150 },
        { id: "score", minWidth: 76 },
        { id: "duration", minWidth: 96 },
        { id: "time", minWidth: 108 },
        { id: "text", minWidth: 72 },
        { id: "audio", minWidth: 72 },
    ];
    const legacyTranscriptionAiSettingsStorageKey = `call-center.transcription-ai-settings:${window.location.pathname}`;
    const transcriptionAiSettingsStorageKey = `call-center.ai-settings.transcription-result:${window.location.pathname}`;
    const transcriptionLlmSettingsStorageKey = `call-center.llm-settings.evaluation:${window.location.pathname}`;
    const defaultTranscriptionAiPrompt = "Знайди тільки точкові виправлення в транскрибації: очевидні орфографічні помилки, російські слова, які треба замінити українськими відповідниками, і неіснуючі або неправильно розпізнані слова, якщо правильний варіант очевидний з контексту. Не змінюй сенс, структуру реплік, імена, телефони, цифри, артикули та бренди.";
    let hasCalls = calls.length > 0;
    let selectedCallId = calls[0]?.id ?? null;
    let checklists = Array.isArray(initialChecklists) ? initialChecklists : [];
    let storedChecklistSelectionId = readStoredChecklistSelectionId();
    let transcriptionAiSettingsState = readStoredTranscriptionAiSettings();
    let transcriptionLlmSettingsState = readStoredTranscriptionLlmSettings();
    let transcriptionAiSettingsDraft = null;
    let transcriptionLlmSettingsDraft = null;
    let activeChecklistId = null;
    let activeChecklistRenameId = null;
    let pendingChecklistSelectionId = null;
    let pendingChecklistRenameId = null;
    let pendingChecklistDeleteId = null;
    let isChecklistRenameSaving = false;
    let isChecklistDeleteSubmitting = false;
    let phoneSearchValue = "";
    let employeeFilterValue = "all";
    let rangeStart = null;
    let rangeEnd = null;
    let draftRangeStart = null;
    let draftRangeEnd = null;
    let calendarViewDate = null;
    let callsPage = 1;
    let managersPage = 1;
    let sortField = "time";
    let sortDirection = isInteractionHistoryMode ? "asc" : "desc";
    let hasCustomDateRange = false;
    let isChecklistEditorDirty = false;
    let isSettingsDirty = false;
    let pageScrollLockY = 0;
    let isBootstrapRefreshing = false;
    let lastBootstrapSyncAt = Date.now();
    let activeModalKind = null;
    let activeModalCallId = null;
    const audioRefreshCallIds = new Set();
    let activeColumnResize = null;
    let interactionCountIndex = new Map();
    let interactionNumberIndex = new Map();
    let activeEvaluationJobId = null;
    let activeTranscriptionTaskId = null;
    let activeTranscriptionComparisonRunId = "";
    let activeTranscriptionRequestController = null;
    let activeEvaluationRequestController = null;
    let activeEvaluationPollController = null;
    let isStoppingTranscriptionTasks = false;
    let isTranscriptionAiRewriteRunning = false;
    let activeTranscriptionAiRewriteController = null;
    let transcriptionAiSourceTextSnapshot = "";
    let transcriptionAiThinkingText = "";
    let transcriptionAiResponseText = "";
    let isStoppingTranscriptionAiRewrite = false;
    const callsPerPage = isInteractionHistoryMode ? Math.max(14, calls.length) : 14;
    const managersPerPage = 10;
    const pageBootstrapStaleAfterMs = 15000;
    const weekdayLabels = ["Пн", "Вт", "Ср", "Чт", "Пт", "Сб", "Нд"];
    const monthLabels = [
        "Січень", "Лютий", "Березень", "Квітень", "Травень", "Червень",
        "Липень", "Серпень", "Вересень", "Жовтень", "Листопад", "Грудень"
    ];

    function scoreClass(score) {
        const numericScore = numericScoreValue(score);

        if (numericScore === null) {
            return "is-muted";
        }

        if (numericScore >= 90) {
            return "score-high";
        }

        if (numericScore >= 85) {
            return "score-mid";
        }

        return "score-low";
    }

    function numericScoreValue(score) {
        const numericScore = Number(score);

        return Number.isFinite(numericScore) ? numericScore : null;
    }

    function displayScore(score) {
        const numericScore = numericScoreValue(score);

        return numericScore === null ? "—" : String(Math.round(numericScore));
    }

    function readForcedTranscriptionRequest() {
        const params = new URLSearchParams(window.location.search);

        if (params.get("force_transcription") !== "1") {
            return null;
        }

        return {
            callId: Number(params.get("call_id") || 0),
            generalCallId: String(params.get("general_call_id") || "").trim(),
            autorun: params.get("autorun") === "1",
        };
    }

    function clearForcedTranscriptionRequest() {
        const url = new URL(window.location.href);

        [
            "force_transcription",
            "call_id",
            "general_call_id",
            "autorun",
        ].forEach((key) => url.searchParams.delete(key));

        window.history.replaceState({}, document.title, `${url.pathname}${url.search}${url.hash}`);
    }

    function hasUsableAudioUrl(call) {
        return typeof call?.audioUrl === "string" && call.audioUrl.trim() !== "";
    }

    function hasFallbackAudioUrl(call) {
        return typeof call?.audioFallbackUrl === "string" && call.audioFallbackUrl.trim() !== "";
    }

    function callAudioRefreshEndpoint(callId) {
        const encodedCallId = encodeURIComponent(String(callId || "").trim());

        if (!callAudioEndpoint || encodedCallId === "") {
            return "";
        }

        return callAudioEndpoint.replace("__CALL_ID__", encodedCallId);
    }

    function applyCallAudioPayload(callId, payload) {
        const index = calls.findIndex((item) => item.id === callId);

        if (index === -1 || !payload || typeof payload !== "object") {
            return null;
        }

        const currentCall = calls[index];
        const updatedCall = {
            ...currentCall,
            audioUrl: payload.audioUrl ?? null,
            audioFallbackUrl: payload.audioFallbackUrl ?? currentCall.audioFallbackUrl ?? null,
            audioOverlayUrl: payload.audioOverlayUrl ?? currentCall.audioOverlayUrl ?? null,
            audioStatus: payload.audioStatus || currentCall.audioStatus,
            generalCallId: payload.generalCallId || currentCall.generalCallId,
            recordingStatus: payload.recordingStatus ?? currentCall.recordingStatus ?? "",
        };

        calls = [
            ...calls.slice(0, index),
            updatedCall,
            ...calls.slice(index + 1),
        ];

        return updatedCall;
    }

    async function refreshCallAudioUrl(call) {
        const callId = Number(call?.id);
        const endpoint = callAudioRefreshEndpoint(callId);

        if (!endpoint || !Number.isFinite(callId)) {
            return;
        }

        try {
            const response = await fetch(endpoint, {
                headers: {
                    Accept: "application/json"
                },
                cache: "no-store"
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(payload.message || `Audio URL refresh failed with status ${response.status}`);
            }

            applyCallAudioPayload(callId, payload);
            renderRows();
        } catch (error) {
            // Keep the fallback Binotel cabinet link visible when direct URL refresh fails.
        } finally {
            audioRefreshCallIds.delete(callId);

            if (!callModal.hidden && activeModalKind === "audio" && activeModalCallId === callId) {
                const updatedCall = findCall(callId);

                if (updatedCall) {
                    openAudioModal(updatedCall, { refresh: false });
                }
            }
        }
    }

    function resolveForcedTranscriptionCall(request) {
        if (!request) {
            return null;
        }

        if (Number.isFinite(request.callId) && request.callId > 0) {
            const byId = findCall(request.callId);

            if (byId) {
                return byId;
            }
        }

        const generalCallId = String(request.generalCallId || "").trim();

        if (generalCallId === "") {
            return null;
        }

        return calls.find((call) => String(call?.generalCallId || "").trim() === generalCallId) || null;
    }

    async function applyForcedTranscriptionRequest() {
        if (!forcedTranscriptionRequest) {
            return;
        }

        activateSection("transcriptionSection", { syncUrl: true });

        const targetCall = resolveForcedTranscriptionCall(forcedTranscriptionRequest);
        clearForcedTranscriptionRequest();

        if (!targetCall) {
            setTranscriptionFeedback("Не вдалося знайти вибраний дзвінок для Транскрибації 1. Оновіть сторінку й спробуйте ще раз.", "is-error");
            return;
        }

        selectedCallId = Number(targetCall.id) || selectedCallId;
        renderRows();

        let preparedCall = findCall(selectedCallId) || targetCall;
        let audioUrl = String(preparedCall?.audioUrl || "").trim();

        if (audioUrl === "" && callAudioRefreshEndpoint(preparedCall.id) !== "") {
            audioRefreshCallIds.add(Number(preparedCall.id));
            await refreshCallAudioUrl(preparedCall);
            preparedCall = findCall(selectedCallId) || preparedCall;
            audioUrl = String(preparedCall?.audioUrl || "").trim();
        }

        if (audioUrl === "") {
            setTranscriptionFeedback("Для вибраного дзвінка ще немає прямого посилання на аудіо. Відкрийте його вручну або дочекайтесь синхронізації з Binotel.", "is-error");
            return;
        }

        if (transcriptionUrl) {
            transcriptionUrl.value = audioUrl;
        }

        setTranscriptionFeedback(
            `У форму підставлено дзвінок ${preparedCall.generalCallId || preparedCall.caller || ""} для Транскрибації 1.`,
            "is-success",
        );

        if (forcedTranscriptionRequest.autorun) {
            await runTranscription();
        }
    }

    function renderAudioPlayer(call) {
        const isRefreshingAudioUrl = audioRefreshCallIds.has(Number(call?.id));

        if (hasUsableAudioUrl(call)) {
            return `
                <audio controls preload="none" style="width: 100%;">
                    <source src="${escapeAttribute(call.audioUrl)}" type="audio/mpeg">
                    Ваш браузер не підтримує відтворення аудіо.
                </audio>
                <p style="margin-top: 12px;">
                    <a href="${escapeAttribute(call.audioUrl)}" target="_blank" rel="noopener noreferrer">Відкрити прямий URL запису</a>
                </p>
            `;
        }

        if (isRefreshingAudioUrl) {
            return `
                <p>Запитуємо прямий URL запису у Binotel за General Call ID з бази...</p>
                ${hasFallbackAudioUrl(call) ? `
                    <p style="margin-top: 12px;">
                        <a href="${escapeAttribute(call.audioFallbackUrl)}" target="_blank" rel="noopener noreferrer">Відкрити запис у Binotel</a>
                    </p>
                ` : ""}
            `;
        }

        if (hasFallbackAudioUrl(call)) {
            return `
                <p>Прямий URL запису ще не отримано. Поки доступне резервне посилання з кабінету Binotel.</p>
                <p style="margin-top: 12px;">
                    <a href="${escapeAttribute(call.audioFallbackUrl)}" target="_blank" rel="noopener noreferrer">Відкрити запис у Binotel</a>
                </p>
            `;
        }

        return `
            <p>Запис ще недоступний. Binotel поки не повернув прямий URL для цього дзвінка.</p>
        `;
    }

    function knownSectionIds() {
        return contentSections.map((section) => section.id);
    }

    function resolveSectionId(value) {
        const normalized = String(value || "").replace(/^#/, "").trim();
        return knownSectionIds().includes(normalized) ? normalized : "callsSection";
    }

    function activateSection(targetId, { syncUrl = true } = {}) {
        const resolvedId = resolveSectionId(targetId);

        navItems.forEach((item) => {
            const isActive = item.dataset.sectionTarget === resolvedId;
            item.classList.toggle("active", isActive);
            item.setAttribute("aria-current", isActive ? "page" : "false");
        });

        contentSections.forEach((section) => {
            section.classList.toggle("active", section.id === resolvedId);
        });

        if (syncUrl && window.location.hash !== `#${resolvedId}`) {
            history.pushState(null, "", `#${resolvedId}`);
        }
    }

    function escapeHtml(value) {
        return String(value)
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#039;");
    }

    function findCall(callId) {
        return calls.find((item) => item.id === callId);
    }

    function parseDateOnly(value) {
        const [day, month, year] = value.split(".").map(Number);
        return new Date(year, month - 1, day);
    }

    function parseDurationToSeconds(duration) {
        const parts = duration.split(":").map(Number);
        return parts.reduce((sum, part) => (sum * 60) + part, 0);
    }

    function normalizePhone(value) {
        return String(value ?? "").replace(/\D+/g, "");
    }

    function normalizeInteractionPhone(value) {
        const digits = normalizePhone(value);

        return digits.length === 12 && digits.startsWith("380") ? digits.slice(2) : digits;
    }

    function normalizeInteractionManagerName(value) {
        return normalizeManagerName(value)
            .replace(/\s+/g, " ")
            .toLowerCase();
    }

    function normalizeInteractionManagerKey(call) {
        const employeeMeta = String(call?.employeeMeta ?? "").trim();
        const employeeMetaKey = employeeMeta.includes("@") || normalizePhone(employeeMeta) !== ""
            ? normalizeInteractionManagerName(employeeMeta)
            : "";

        return employeeMetaKey || normalizeInteractionManagerName(call?.employee);
    }

    function interactionCountKey(call) {
        const phone = normalizeInteractionPhone(call?.caller);
        const manager = normalizeInteractionManagerKey(call);

        return phone && manager ? `${phone}::${manager}` : "";
    }

    function rebuildInteractionCountIndex() {
        const nextCountIndex = new Map();
        const nextNumberIndex = new Map();
        const groupedCalls = new Map();

        calls.forEach((call) => {
            const key = interactionCountKey(call);

            if (key) {
                nextCountIndex.set(key, (nextCountIndex.get(key) || 0) + 1);
                groupedCalls.set(key, [...(groupedCalls.get(key) || []), call]);
            }
        });

        groupedCalls.forEach((items) => {
            [...items]
                .sort((left, right) => {
                    const leftTime = parseDateTime(left);
                    const rightTime = parseDateTime(right);
                    const safeLeftTime = Number.isFinite(leftTime) ? leftTime : 0;
                    const safeRightTime = Number.isFinite(rightTime) ? rightTime : 0;

                    if (safeLeftTime !== safeRightTime) {
                        return safeLeftTime - safeRightTime;
                    }

                    return Number(left.id || 0) - Number(right.id || 0);
                })
                .forEach((call, index) => {
                    nextNumberIndex.set(call.id, index + 1);
                });
        });

        interactionCountIndex = nextCountIndex;
        interactionNumberIndex = nextNumberIndex;
    }

    function interactionCountForCall(call) {
        const key = interactionCountKey(call);

        return key ? (interactionCountIndex.get(key) || 1) : null;
    }

    function interactionNumberForCall(call) {
        const storedNumber = Number.parseInt(call?.interactionNumber, 10);

        if (Number.isFinite(storedNumber) && storedNumber > 0) {
            return storedNumber;
        }

        return interactionNumberIndex.get(call?.id) || null;
    }

    function readInteractionHistoryRequest() {
        const params = new URLSearchParams(window.location.search);
        const phone = params.get("interactionPhone");
        const manager = params.get("interactionManager");

        if (!phone || !manager) {
            return null;
        }

        return {
            phone: normalizeInteractionPhone(phone),
            manager: String(manager).trim().toLowerCase()
        };
    }

    function filterInteractionHistoryRequestCalls(request, items) {
        if (!request) {
            return items;
        }

        return items.filter((call) => (
            normalizeInteractionPhone(call?.caller) === request.phone
            && normalizeInteractionManagerKey(call) === request.manager
        ));
    }

    function buildInteractionHistoryUrl(call) {
        const url = new URL(window.location.pathname, window.location.origin);
        url.searchParams.set("interactionPhone", normalizeInteractionPhone(call?.caller));
        url.searchParams.set("interactionManager", normalizeInteractionManagerKey(call));
        url.hash = "callsSection";

        return url.toString();
    }

    function applyInteractionHistoryMode() {
        if (!isInteractionHistoryMode) {
            return;
        }

        document.body.classList.add("is-interaction-history-page");
        activateSection("callsSection", { syncUrl: false });

        const sortedHistoryCalls = [...calls].sort((left, right) => parseDateTime(left) - parseDateTime(right));
        const firstCall = sortedHistoryCalls[0] || null;
        const lastCall = sortedHistoryCalls[sortedHistoryCalls.length - 1] || null;
        const displayPhone = firstCall?.caller || interactionHistoryRequest.phone;
        const displayManager = firstCall ? normalizeManagerName(firstCall.employee) : interactionHistoryRequest.manager;
        const periodText = firstCall && lastCall
            ? `${firstCall.date} ${firstCall.time} - ${lastCall.date} ${lastCall.time}`
            : "Немає дзвінків";

        document.title = `Історія ${displayPhone} - ${displayManager}`;
        document.querySelector("#callsSection .card-title").textContent = "Історія взаємодій";

        const cardHeadMain = document.querySelector("#callsSection .card-head-main");
        const existingText = cardHeadMain?.querySelector(".card-text");
        if (existingText) {
            existingText.remove();
        }

        cardHeadMain?.insertAdjacentHTML(
            "beforeend",
            `<p class="card-text">Клієнт: ${escapeHtml(displayPhone)}. Менеджер: ${escapeHtml(displayManager)}. Період: ${escapeHtml(periodText)}.</p>`
        );
    }

    function escapeAttribute(value) {
        return String(value)
            .replaceAll("&", "&amp;")
            .replaceAll('"', "&quot;");
    }

    function languageLabel(value) {
        return transcriptionLanguageLabels[value] || value || "Автовизначення";
    }

    function markChecklistEditorDirty() {
        isChecklistEditorDirty = true;
    }

    function markSettingsDirty() {
        isSettingsDirty = true;
    }

    function readStoredChecklistSelectionId() {
        try {
            return String(window.localStorage?.getItem(checklistSelectionStorageKey) || "").trim();
        } catch (error) {
            return "";
        }
    }

    function writeStoredChecklistSelectionId(checklistId) {
        storedChecklistSelectionId = typeof checklistId === "string" ? checklistId.trim() : "";

        try {
            if (storedChecklistSelectionId !== "") {
                window.localStorage?.setItem(checklistSelectionStorageKey, storedChecklistSelectionId);
                return;
            }

            window.localStorage?.removeItem(checklistSelectionStorageKey);
        } catch (error) {
            // Ignore storage failures and keep in-memory state only.
        }
    }

    function availableTranscriptionAiModels(preferredModel = "") {
        const currentValue = String(preferredModel || transcriptionAiSettingsState?.model || settingsModel?.value || transcriptionSettings?.llm_model || "").trim();
        const availableModels = Array.isArray(transcriptionSettings?.llm_available_models)
            ? transcriptionSettings.llm_available_models
            : [];

        return [...new Set([
            ...availableModels.filter((value) => typeof value === "string" && value.trim() !== ""),
            currentValue
        ].filter(Boolean))];
    }

    function normalizeTranscriptionAiNumber(value, fallback, min, max, { integer = false } = {}) {
        const fallbackNumber = Number(fallback);
        const numericFallback = Number.isFinite(fallbackNumber) ? fallbackNumber : min;
        const numeric = value === "" || value === null || value === undefined
            ? Number.NaN
            : Number(value);
        const bounded = Number.isFinite(numeric)
            ? Math.max(min, Math.min(max, numeric))
            : Math.max(min, Math.min(max, numericFallback));

        return integer ? Math.round(bounded) : bounded;
    }

    function normalizeTranscriptionAiOptionalInteger(value, min, max) {
        if (value === "" || value === null || value === undefined) {
            return null;
        }

        const numeric = Number(value);
        if (!Number.isFinite(numeric)) {
            return null;
        }

        return Math.max(min, Math.min(max, Math.round(numeric)));
    }

    function normalizeTranscriptionAiBoolean(value, fallback = false) {
        if (typeof value === "boolean") {
            return value;
        }

        if (typeof value === "string") {
            return ["1", "true", "yes", "on"].includes(value.trim().toLowerCase());
        }

        if (typeof value === "number") {
            return value !== 0;
        }

        return Boolean(fallback);
    }

    function normalizeGenerationSettingsByModel(rawMap = {}, normalizeGenerationSettings) {
        if (!rawMap || typeof rawMap !== "object" || Array.isArray(rawMap)) {
            return {};
        }

        return Object.entries(rawMap).reduce((map, [model, settings]) => {
            const modelName = String(model || "").trim();

            if (modelName !== "") {
                map[modelName] = normalizeGenerationSettings(settings);
            }

            return map;
        }, {});
    }

    function generationSettingsForModel(settingsState = {}, model = "", normalizeGenerationSettings, defaultGenerationSettings) {
        const modelName = String(model || "").trim();
        const modelSettings = settingsState?.generation_settings_by_model && typeof settingsState.generation_settings_by_model === "object"
            ? settingsState.generation_settings_by_model
            : {};

        if (modelName !== "" && modelSettings[modelName]) {
            return normalizeGenerationSettings(modelSettings[modelName]);
        }

        return normalizeGenerationSettings(defaultGenerationSettings());
    }

    function defaultTranscriptionAiGenerationSettings() {
        const configuredNumPredict = Number(transcriptionSettings?.llm_num_predict);
        const defaultNumPredict = configuredNumPredict === -1
            ? -1
            : Math.max(Number.isFinite(configuredNumPredict) ? configuredNumPredict : 1500, 1500);

        return {
            thinking_enabled: false,
            temperature: normalizeTranscriptionAiNumber(transcriptionSettings?.llm_temperature, 0.2, 0, 2),
            num_ctx: normalizeTranscriptionAiNumber(transcriptionSettings?.llm_num_ctx, 4096, 256, 131072, { integer: true }),
            top_k: normalizeTranscriptionAiNumber(transcriptionSettings?.llm_top_k, 40, 1, 500, { integer: true }),
            top_p: normalizeTranscriptionAiNumber(transcriptionSettings?.llm_top_p, 0.9, 0, 1),
            repeat_penalty: normalizeTranscriptionAiNumber(transcriptionSettings?.llm_repeat_penalty, 1.1, 0, 5),
            num_predict: normalizeTranscriptionAiNumber(defaultNumPredict, 1500, -1, 32768, { integer: true }),
            seed: normalizeTranscriptionAiOptionalInteger(transcriptionSettings?.llm_seed, -2147483648, 2147483647),
            timeout_seconds: normalizeTranscriptionAiNumber(transcriptionSettings?.llm_timeout_seconds, 600, 15, 3600, { integer: true }),
        };
    }

    function normalizeTranscriptionAiGenerationSettings(rawSettings = {}) {
        const defaults = defaultTranscriptionAiGenerationSettings();
        const source = rawSettings && typeof rawSettings === "object" ? rawSettings : {};

        return {
            thinking_enabled: false,
            temperature: normalizeTranscriptionAiNumber(source.temperature, defaults.temperature, 0, 2),
            num_ctx: normalizeTranscriptionAiNumber(source.num_ctx, defaults.num_ctx, 256, 131072, { integer: true }),
            top_k: normalizeTranscriptionAiNumber(source.top_k, defaults.top_k, 1, 500, { integer: true }),
            top_p: normalizeTranscriptionAiNumber(source.top_p, defaults.top_p, 0, 1),
            repeat_penalty: normalizeTranscriptionAiNumber(source.repeat_penalty ?? source.repetition_penalty, defaults.repeat_penalty, 0, 5),
            num_predict: normalizeTranscriptionAiNumber(source.num_predict ?? source.max_new_tokens, defaults.num_predict, -1, 32768, { integer: true }),
            seed: normalizeTranscriptionAiOptionalInteger(source.seed, -2147483648, 2147483647),
            timeout_seconds: normalizeTranscriptionAiNumber(source.timeout_seconds, defaults.timeout_seconds, 15, 3600, { integer: true }),
        };
    }

    function normalizeTranscriptionAiSettings(rawSettings = {}) {
        const resolvedModel = String(rawSettings?.model || "").trim()
            || String(settingsModel?.value || "").trim()
            || String(transcriptionSettings?.llm_model || "").trim();
        const rawPrompt = typeof rawSettings?.prompt === "string"
            ? rawSettings.prompt
            : "";
        const resolvedPrompt = rawPrompt.trim() !== ""
            ? rawPrompt
            : defaultTranscriptionAiPrompt;
        const rawGenerationSettings = rawSettings?.generation_settings && typeof rawSettings.generation_settings === "object"
            ? rawSettings.generation_settings
            : rawSettings;
        const generationSettingsByModel = normalizeGenerationSettingsByModel(
            rawSettings?.generation_settings_by_model,
            normalizeTranscriptionAiGenerationSettings
        );
        const resolvedGenerationSettings = generationSettingsByModel[resolvedModel]
            || normalizeTranscriptionAiGenerationSettings(rawGenerationSettings);

        if (resolvedModel !== "") {
            generationSettingsByModel[resolvedModel] = resolvedGenerationSettings;
        }

        return {
            model: resolvedModel,
            prompt: resolvedPrompt,
            generation_settings: resolvedGenerationSettings,
            generation_settings_by_model: generationSettingsByModel,
        };
    }

    function readStoredTranscriptionAiSettings() {
        try {
            const rawValue = window.localStorage?.getItem(transcriptionAiSettingsStorageKey)
                || window.localStorage?.getItem(legacyTranscriptionAiSettingsStorageKey);
            if (!rawValue) {
                return normalizeTranscriptionAiSettings();
            }

            const parsed = JSON.parse(rawValue);

            return normalizeTranscriptionAiSettings(parsed);
        } catch (error) {
            return normalizeTranscriptionAiSettings();
        }
    }

    function writeStoredTranscriptionAiSettings(nextSettings = {}) {
        transcriptionAiSettingsState = normalizeTranscriptionAiSettings(nextSettings);

        try {
            const serializedSettings = JSON.stringify(transcriptionAiSettingsState);
            window.localStorage?.setItem(transcriptionAiSettingsStorageKey, serializedSettings);

            return window.localStorage?.getItem(transcriptionAiSettingsStorageKey) === serializedSettings;
        } catch (error) {
            return false;
        }
    }

    function collectTranscriptionAiGenerationSettingsFromForm(fallbackSettings = {}) {
        const fallback = normalizeTranscriptionAiGenerationSettings(fallbackSettings);

        return normalizeTranscriptionAiGenerationSettings({
            thinking_enabled: false,
            temperature: transcriptionAiTemperature?.value ?? fallback.temperature,
            num_ctx: transcriptionAiNumCtx?.value ?? fallback.num_ctx,
            top_k: transcriptionAiTopK?.value ?? fallback.top_k,
            top_p: transcriptionAiTopP?.value ?? fallback.top_p,
            repeat_penalty: transcriptionAiRepeatPenalty?.value ?? fallback.repeat_penalty,
            num_predict: transcriptionAiNumPredict?.value ?? fallback.num_predict,
            seed: transcriptionAiSeed?.value?.trim() ?? fallback.seed,
            timeout_seconds: transcriptionAiTimeoutSeconds?.value ?? fallback.timeout_seconds,
        });
    }

    function collectTranscriptionAiSettingsFromForm() {
        const baseSettings = normalizeTranscriptionAiSettings(transcriptionAiSettingsDraft || transcriptionAiSettingsState);
        const selectedModel = String(transcriptionAiModel?.value || baseSettings.model || "").trim();
        const generationSettings = collectTranscriptionAiGenerationSettingsFromForm(baseSettings.generation_settings);
        const generationSettingsByModel = {
            ...(baseSettings.generation_settings_by_model || {}),
        };

        if (selectedModel !== "") {
            generationSettingsByModel[selectedModel] = generationSettings;
        }

        return normalizeTranscriptionAiSettings({
            model: selectedModel,
            prompt: transcriptionAiPrompt?.value ?? baseSettings.prompt ?? defaultTranscriptionAiPrompt,
            generation_settings: generationSettings,
            generation_settings_by_model: generationSettingsByModel,
        });
    }

    function syncTranscriptionAiSettingsForm(sourceSettings = transcriptionAiSettingsState) {
        const settings = normalizeTranscriptionAiSettings(sourceSettings);
        const generationSettings = normalizeTranscriptionAiGenerationSettings(settings.generation_settings);

        if (transcriptionAiModel) {
            const preferredModel = String(settings.model || settingsModel?.value || transcriptionSettings?.llm_model || "").trim();
            syncSelectOptions(transcriptionAiModel, availableTranscriptionAiModels(preferredModel), preferredModel);
        }

        if (transcriptionAiPrompt) {
            transcriptionAiPrompt.value = settings.prompt ?? defaultTranscriptionAiPrompt;
        }

        if (transcriptionAiThinkingEnabled) {
            transcriptionAiThinkingEnabled.checked = false;
            transcriptionAiThinkingEnabled.disabled = true;
        }

        if (transcriptionAiTemperature) {
            transcriptionAiTemperature.value = String(generationSettings.temperature);
        }

        if (transcriptionAiNumCtx) {
            transcriptionAiNumCtx.value = String(generationSettings.num_ctx);
        }

        if (transcriptionAiTopK) {
            transcriptionAiTopK.value = String(generationSettings.top_k);
        }

        if (transcriptionAiTopP) {
            transcriptionAiTopP.value = String(generationSettings.top_p);
        }

        if (transcriptionAiRepeatPenalty) {
            transcriptionAiRepeatPenalty.value = String(generationSettings.repeat_penalty);
        }

        if (transcriptionAiNumPredict) {
            transcriptionAiNumPredict.value = String(generationSettings.num_predict);
        }

        if (transcriptionAiSeed) {
            transcriptionAiSeed.value = generationSettings.seed ?? "";
        }

        if (transcriptionAiTimeoutSeconds) {
            transcriptionAiTimeoutSeconds.value = String(generationSettings.timeout_seconds);
        }

        syncTranscriptionAiGenerationSliderValues();
    }

    function syncTranscriptionAiGenerationSliderValues() {
        if (transcriptionAiTemperature && transcriptionAiTemperatureValue) {
            transcriptionAiTemperatureValue.textContent = formatSliderNumber(transcriptionAiTemperature.value, 1);
        }

        if (transcriptionAiNumCtx && transcriptionAiNumCtxValue) {
            transcriptionAiNumCtxValue.textContent = formatSliderNumber(transcriptionAiNumCtx.value, 0);
        }
    }

    function switchTranscriptionAiSettingsModel(nextModel) {
        const modelName = String(nextModel || "").trim();
        const draft = normalizeTranscriptionAiSettings(transcriptionAiSettingsDraft || transcriptionAiSettingsState);
        const previousModel = String(draft.model || "").trim();
        const generationSettingsByModel = {
            ...(draft.generation_settings_by_model || {}),
        };

        if (previousModel !== "") {
            generationSettingsByModel[previousModel] = collectTranscriptionAiGenerationSettingsFromForm(draft.generation_settings);
        }

        const nextGenerationSettings = generationSettingsForModel(
            { generation_settings_by_model: generationSettingsByModel },
            modelName,
            normalizeTranscriptionAiGenerationSettings,
            defaultTranscriptionAiGenerationSettings,
        );

        if (modelName !== "") {
            generationSettingsByModel[modelName] = nextGenerationSettings;
        }

        transcriptionAiSettingsDraft = normalizeTranscriptionAiSettings({
            ...draft,
            model: modelName,
            prompt: transcriptionAiPrompt?.value ?? draft.prompt,
            generation_settings: nextGenerationSettings,
            generation_settings_by_model: generationSettingsByModel,
        });
        syncTranscriptionAiSettingsForm(transcriptionAiSettingsDraft);
    }

    function resetTranscriptionAiCurrentModelSettingsToDefaults() {
        const draft = normalizeTranscriptionAiSettings(transcriptionAiSettingsDraft || transcriptionAiSettingsState);
        const modelName = String(transcriptionAiModel?.value || draft.model || "").trim();

        if (modelName === "") {
            setTranscriptionFeedback("Оберіть AI-модель, щоб скинути її параметри до дефолту.", "is-error");
            transcriptionAiModel?.focus();
            return;
        }

        const defaultGenerationSettings = normalizeTranscriptionAiGenerationSettings(defaultTranscriptionAiGenerationSettings());
        const generationSettingsByModel = {
            ...(draft.generation_settings_by_model || {}),
            [modelName]: defaultGenerationSettings,
        };

        transcriptionAiSettingsDraft = normalizeTranscriptionAiSettings({
            ...draft,
            model: modelName,
            prompt: transcriptionAiPrompt?.value ?? draft.prompt,
            generation_settings: defaultGenerationSettings,
            generation_settings_by_model: generationSettingsByModel,
        });
        syncTranscriptionAiSettingsForm(transcriptionAiSettingsDraft);
        setTranscriptionFeedback(`Параметри моделі ${modelName} у блоці AI скинуто до рекомендованого дефолту. Натисніть “Зберегти”, щоб зафіксувати.`, "");
    }

    function availableTranscriptionLlmModels(preferredModel = "") {
        const currentValue = String(preferredModel || transcriptionLlmSettingsState?.model || transcriptionSettings?.llm_model || "").trim();
        const availableModels = Array.isArray(transcriptionSettings?.llm_available_models)
            ? transcriptionSettings.llm_available_models
            : [];

        return [...new Set([
            ...availableModels.filter((value) => typeof value === "string" && value.trim() !== ""),
            currentValue
        ].filter(Boolean))];
    }

    function defaultTranscriptionLlmGenerationSettings() {
        return {
            thinking_enabled: false,
            temperature: normalizeTranscriptionAiNumber(transcriptionSettings?.llm_temperature, 0.2, 0, 2),
            num_ctx: normalizeTranscriptionAiNumber(transcriptionSettings?.llm_num_ctx, 4096, 256, 131072, { integer: true }),
            top_k: normalizeTranscriptionAiNumber(transcriptionSettings?.llm_top_k, 40, 1, 500, { integer: true }),
            top_p: normalizeTranscriptionAiNumber(transcriptionSettings?.llm_top_p, 0.9, 0, 1),
            repeat_penalty: normalizeTranscriptionAiNumber(transcriptionSettings?.llm_repeat_penalty, 1.1, 0, 5),
            num_predict: normalizeTranscriptionAiNumber(transcriptionSettings?.llm_num_predict, 1500, -1, 32768, { integer: true }),
            seed: normalizeTranscriptionAiOptionalInteger(transcriptionSettings?.llm_seed, -2147483648, 2147483647),
            timeout_seconds: normalizeTranscriptionAiNumber(transcriptionSettings?.llm_timeout_seconds, 600, 15, 3600, { integer: true }),
        };
    }

    function normalizeTranscriptionLlmGenerationSettings(rawSettings = {}) {
        const defaults = defaultTranscriptionLlmGenerationSettings();
        const source = rawSettings && typeof rawSettings === "object" ? rawSettings : {};

        return {
            thinking_enabled: false,
            temperature: normalizeTranscriptionAiNumber(source.temperature, defaults.temperature, 0, 2),
            num_ctx: normalizeTranscriptionAiNumber(source.num_ctx, defaults.num_ctx, 256, 131072, { integer: true }),
            top_k: normalizeTranscriptionAiNumber(source.top_k, defaults.top_k, 1, 500, { integer: true }),
            top_p: normalizeTranscriptionAiNumber(source.top_p, defaults.top_p, 0, 1),
            repeat_penalty: normalizeTranscriptionAiNumber(source.repeat_penalty ?? source.repetition_penalty, defaults.repeat_penalty, 0, 5),
            num_predict: normalizeTranscriptionAiNumber(source.num_predict ?? source.max_new_tokens, defaults.num_predict, -1, 32768, { integer: true }),
            seed: normalizeTranscriptionAiOptionalInteger(source.seed, -2147483648, 2147483647),
            timeout_seconds: normalizeTranscriptionAiNumber(source.timeout_seconds, defaults.timeout_seconds, 15, 3600, { integer: true }),
        };
    }

    function normalizeTranscriptionLlmSettings(rawSettings = {}) {
        const resolvedModel = String(rawSettings?.model || "").trim()
            || String(transcriptionSettings?.llm_model || "").trim();
        const resolvedSystemPrompt = typeof rawSettings?.system_prompt === "string"
            ? rawSettings.system_prompt
            : defaultTranscriptionLlmSystemPrompt;
        const rawGenerationSettings = rawSettings?.generation_settings && typeof rawSettings.generation_settings === "object"
            ? rawSettings.generation_settings
            : rawSettings;
        const generationSettingsByModel = normalizeGenerationSettingsByModel(
            rawSettings?.generation_settings_by_model,
            normalizeTranscriptionLlmGenerationSettings
        );
        const resolvedGenerationSettings = generationSettingsByModel[resolvedModel]
            || normalizeTranscriptionLlmGenerationSettings(rawGenerationSettings);

        if (resolvedModel !== "") {
            generationSettingsByModel[resolvedModel] = resolvedGenerationSettings;
        }

        return {
            model: resolvedModel,
            system_prompt: resolvedSystemPrompt,
            generation_settings: resolvedGenerationSettings,
            generation_settings_by_model: generationSettingsByModel,
        };
    }

    function readStoredTranscriptionLlmSettings() {
        try {
            const rawValue = window.localStorage?.getItem(transcriptionLlmSettingsStorageKey);
            if (!rawValue) {
                return normalizeTranscriptionLlmSettings();
            }

            return normalizeTranscriptionLlmSettings(JSON.parse(rawValue));
        } catch (error) {
            return normalizeTranscriptionLlmSettings();
        }
    }

    function writeStoredTranscriptionLlmSettings(nextSettings = {}) {
        transcriptionLlmSettingsState = normalizeTranscriptionLlmSettings(nextSettings);

        try {
            const serializedSettings = JSON.stringify(transcriptionLlmSettingsState);
            window.localStorage?.setItem(transcriptionLlmSettingsStorageKey, serializedSettings);

            return window.localStorage?.getItem(transcriptionLlmSettingsStorageKey) === serializedSettings;
        } catch (error) {
            return false;
        }
    }

    function collectTranscriptionLlmGenerationSettingsFromForm(fallbackSettings = {}) {
        const fallback = normalizeTranscriptionLlmGenerationSettings(fallbackSettings);

        return normalizeTranscriptionLlmGenerationSettings({
            thinking_enabled: false,
            temperature: transcriptionLlmTemperature?.value ?? fallback.temperature,
            num_ctx: transcriptionLlmNumCtx?.value ?? fallback.num_ctx,
            top_k: transcriptionLlmTopK?.value ?? fallback.top_k,
            top_p: transcriptionLlmTopP?.value ?? fallback.top_p,
            repeat_penalty: transcriptionLlmRepeatPenalty?.value ?? fallback.repeat_penalty,
            num_predict: transcriptionLlmNumPredict?.value ?? fallback.num_predict,
            seed: transcriptionLlmSeed?.value?.trim() ?? fallback.seed,
            timeout_seconds: transcriptionLlmTimeoutSeconds?.value ?? fallback.timeout_seconds,
        });
    }

    function collectTranscriptionLlmSettingsFromForm() {
        const baseSettings = normalizeTranscriptionLlmSettings(transcriptionLlmSettingsDraft || transcriptionLlmSettingsState);
        const selectedModel = String(transcriptionLlmModel?.value || baseSettings.model || "").trim();
        const generationSettings = collectTranscriptionLlmGenerationSettingsFromForm(baseSettings.generation_settings);
        const generationSettingsByModel = {
            ...(baseSettings.generation_settings_by_model || {}),
        };

        if (selectedModel !== "") {
            generationSettingsByModel[selectedModel] = generationSettings;
        }

        return normalizeTranscriptionLlmSettings({
            model: selectedModel,
            system_prompt: transcriptionLlmSystemPrompt?.value ?? baseSettings.system_prompt ?? defaultTranscriptionLlmSystemPrompt,
            generation_settings: generationSettings,
            generation_settings_by_model: generationSettingsByModel,
        });
    }

    function syncTranscriptionLlmSettingsForm(sourceSettings = transcriptionLlmSettingsState) {
        const settings = normalizeTranscriptionLlmSettings(sourceSettings);
        const generationSettings = normalizeTranscriptionLlmGenerationSettings(settings.generation_settings);

        if (transcriptionLlmModel) {
            const preferredModel = String(settings.model || transcriptionSettings?.llm_model || "").trim();
            syncSelectOptions(transcriptionLlmModel, availableTranscriptionLlmModels(preferredModel), preferredModel);
        }

        if (transcriptionLlmSystemPrompt) {
            transcriptionLlmSystemPrompt.value = settings.system_prompt ?? defaultTranscriptionLlmSystemPrompt;
        }

        if (transcriptionLlmThinkingEnabled) {
            transcriptionLlmThinkingEnabled.checked = false;
            transcriptionLlmThinkingEnabled.disabled = true;
        }

        if (transcriptionLlmTemperature) {
            transcriptionLlmTemperature.value = String(generationSettings.temperature);
        }

        if (transcriptionLlmNumCtx) {
            transcriptionLlmNumCtx.value = String(generationSettings.num_ctx);
        }

        if (transcriptionLlmTopK) {
            transcriptionLlmTopK.value = String(generationSettings.top_k);
        }

        if (transcriptionLlmTopP) {
            transcriptionLlmTopP.value = String(generationSettings.top_p);
        }

        if (transcriptionLlmRepeatPenalty) {
            transcriptionLlmRepeatPenalty.value = String(generationSettings.repeat_penalty);
        }

        if (transcriptionLlmNumPredict) {
            transcriptionLlmNumPredict.value = String(generationSettings.num_predict);
        }

        if (transcriptionLlmSeed) {
            transcriptionLlmSeed.value = generationSettings.seed ?? "";
        }

        if (transcriptionLlmTimeoutSeconds) {
            transcriptionLlmTimeoutSeconds.value = String(generationSettings.timeout_seconds);
        }

        syncTranscriptionLlmGenerationSliderValues();
    }

    function syncTranscriptionLlmGenerationSliderValues() {
        if (transcriptionLlmTemperature && transcriptionLlmTemperatureValue) {
            transcriptionLlmTemperatureValue.textContent = formatSliderNumber(transcriptionLlmTemperature.value, 1);
        }

        if (transcriptionLlmNumCtx && transcriptionLlmNumCtxValue) {
            transcriptionLlmNumCtxValue.textContent = formatSliderNumber(transcriptionLlmNumCtx.value, 0);
        }
    }

    function switchTranscriptionLlmSettingsModel(nextModel) {
        const modelName = String(nextModel || "").trim();
        const draft = normalizeTranscriptionLlmSettings(transcriptionLlmSettingsDraft || transcriptionLlmSettingsState);
        const previousModel = String(draft.model || "").trim();
        const generationSettingsByModel = {
            ...(draft.generation_settings_by_model || {}),
        };

        if (previousModel !== "") {
            generationSettingsByModel[previousModel] = collectTranscriptionLlmGenerationSettingsFromForm(draft.generation_settings);
        }

        const nextGenerationSettings = generationSettingsForModel(
            { generation_settings_by_model: generationSettingsByModel },
            modelName,
            normalizeTranscriptionLlmGenerationSettings,
            defaultTranscriptionLlmGenerationSettings,
        );

        if (modelName !== "") {
            generationSettingsByModel[modelName] = nextGenerationSettings;
        }

        transcriptionLlmSettingsDraft = normalizeTranscriptionLlmSettings({
            ...draft,
            model: modelName,
            system_prompt: transcriptionLlmSystemPrompt?.value ?? draft.system_prompt,
            generation_settings: nextGenerationSettings,
            generation_settings_by_model: generationSettingsByModel,
        });
        syncTranscriptionLlmSettingsForm(transcriptionLlmSettingsDraft);
    }

    function resetTranscriptionLlmCurrentModelSettingsToDefaults() {
        const draft = normalizeTranscriptionLlmSettings(transcriptionLlmSettingsDraft || transcriptionLlmSettingsState);
        const modelName = String(transcriptionLlmModel?.value || draft.model || "").trim();

        if (modelName === "") {
            setTranscriptionFeedback("Оберіть LLM-модель, щоб скинути її параметри до дефолту.", "is-error");
            transcriptionLlmModel?.focus();
            return;
        }

        const defaultGenerationSettings = normalizeTranscriptionLlmGenerationSettings(defaultTranscriptionLlmGenerationSettings());
        const generationSettingsByModel = {
            ...(draft.generation_settings_by_model || {}),
            [modelName]: defaultGenerationSettings,
        };

        transcriptionLlmSettingsDraft = normalizeTranscriptionLlmSettings({
            ...draft,
            model: modelName,
            system_prompt: transcriptionLlmSystemPrompt?.value ?? draft.system_prompt,
            generation_settings: defaultGenerationSettings,
            generation_settings_by_model: generationSettingsByModel,
        });
        syncTranscriptionLlmSettingsForm(transcriptionLlmSettingsDraft);
        setTranscriptionFeedback(`Параметри моделі ${modelName} у блоці LLM скинуто до рекомендованого дефолту. Натисніть “Зберегти”, щоб зафіксувати.`, "");
    }

    function transcriptionAiLiveStatusLabel(status = "pending") {
        switch (String(status || "").trim()) {
            case "running":
                return "В роботі";
            case "completed":
                return "Готово";
            case "failed":
                return "Помилка";
            case "stopped":
                return "Зупинено";
            case "pending":
            default:
                return "Очікування";
        }
    }

    function transcriptionAiLiveStatusTone(status = "pending") {
        if (status === "completed") {
            return "is-completed";
        }

        if (status === "failed" || status === "stopped") {
            return "is-failed";
        }

        if (status === "running") {
            return "is-running";
        }

        return "is-pending";
    }

    function countTranscriptionAiAppliedCorrections(corrections = []) {
        if (!Array.isArray(corrections)) {
            return 0;
        }

        return corrections.reduce((sum, correction) => {
            const count = Number.parseInt(correction?.count, 10);

            return sum + (Number.isFinite(count) && count > 0 ? count : 0);
        }, 0);
    }

    function buildTranscriptionAiCorrectionsPreview(corrections = []) {
        const normalizedCorrections = Array.isArray(corrections) ? corrections : [];
        const appliedCount = countTranscriptionAiAppliedCorrections(normalizedCorrections);

        if (normalizedCorrections.length === 0 || appliedCount === 0) {
            return "AI повернула список виправлень, але скрипт не знайшов безпечних точних автозамін у тексті.";
        }

        const visibleCorrections = normalizedCorrections.slice(0, 120).map((correction) => {
            const original = String(correction?.original || "").trim();
            const replacement = String(correction?.replacement || "").trim();
            const count = Number.parseInt(correction?.count, 10);
            const suffix = Number.isFinite(count) && count > 1
                ? ` (${count} замін)`
                : "";

            return `- ${original} -> ${replacement}${suffix}`;
        });

        if (normalizedCorrections.length > visibleCorrections.length) {
            visibleCorrections.push(`...і ще ${normalizedCorrections.length - visibleCorrections.length} виправлень.`);
        }

        return `Застосовано ${appliedCount} автозамін:\n${visibleCorrections.join("\n")}`;
    }

    function buildTranscriptionAiLivePreviewText() {
        if (transcriptionAiResponseText.trim() !== "") {
            return transcriptionAiResponseText;
        }

        if (transcriptionAiThinkingText.trim() !== "") {
            return `=== THINKING / МІРКУВАННЯ ===\n${transcriptionAiThinkingText}`;
        }

        return "Після запуску AI тут з'явиться живий потік thinking, список виправлень і застосовані автозаміни.";
    }

    function buildTranscriptionAiOllamaPrompt(userPrompt = "", sourceText = "") {
        return `Користувацький промт:
${String(userPrompt || "").trim()}

Текст для обробки:
<<<TEXT
${String(sourceText || "").trim()}
TEXT

Поверни тільки JSON у такому форматі:
{"corrections":[{"original":"слово з помилкою","replacement":"виправлене слово"}]}

Правила:
1. Не переписуй увесь текст і не повертай фінальну версію тексту.
2. У "original" пиши точний фрагмент з тексту, який треба замінити.
3. У "replacement" пиши тільки виправлений фрагмент.
4. Додавай тільки впевнені точкові виправлення: орфографію, заміну російських слів на українські відповідники, а також неіснуючі або неправильно розпізнані слова, якщо правильний варіант очевидний з контексту.
5. Не змінюй структуру реплік, імена, телефони, артикули, цифри, адреси, бренди або сенс.
6. Якщо виправлень немає, поверни {"corrections":[]}.
7. Не додавай markdown, коментарі, пояснення або стару/нову версію тексту.`;
    }

    function buildTranscriptionAiRequestPreview({ model = "", prompt = "", text = "", generationSettings = {} } = {}) {
        const normalizedGenerationSettings = normalizeTranscriptionAiGenerationSettings(generationSettings);
        const options = {
            temperature: normalizedGenerationSettings.temperature,
            num_ctx: normalizedGenerationSettings.num_ctx,
            top_k: normalizedGenerationSettings.top_k,
            top_p: normalizedGenerationSettings.top_p,
            repeat_penalty: normalizedGenerationSettings.repeat_penalty,
            num_predict: normalizedGenerationSettings.num_predict,
        };

        if (normalizedGenerationSettings.seed !== null && normalizedGenerationSettings.seed !== undefined) {
            options.seed = normalizedGenerationSettings.seed;
        }

        return {
            browser_to_backend: {
                endpoint: transcriptionAiRewriteEndpoint || "/api/.../transcriptions/ai-rewrite",
                method: "POST",
                payload: {
                    text: String(text || "").trim(),
                    prompt: String(prompt || "").trim(),
                    model: String(model || "").trim(),
                    generation_settings: normalizedGenerationSettings,
                    stream: true,
                },
            },
            backend_to_ollama: {
                endpoint: "/api/generate",
                payload: {
                    model: String(model || "").trim(),
                    think: false,
                    system: "Ти коректор українських транскриптів дзвінків. Твоє завдання - знайти тільки точкові виправлення: очевидні орфографічні помилки, російські слова, які треба замінити українськими відповідниками, і неіснуючі або неправильно розпізнані слова, які можна впевнено виправити за контекстом. Не переписуй текст повністю, не змінюй сенс, не додавай нові фрази і не виправляй стиль. Відповідай тільки валідним JSON без markdown і пояснень.",
                    prompt: buildTranscriptionAiOllamaPrompt(prompt, text),
                    options,
                    stream: true,
                },
            },
        };
    }

    function hasActiveTranscriptionAiRewrite() {
        return Boolean(isTranscriptionAiRewriteRunning || activeTranscriptionAiRewriteController);
    }

    function syncTranscriptionAiLiveCloseButton() {
        if (!transcriptionAiLiveCloseButton) {
            return;
        }

        const isVisible = Boolean(transcriptionAiLiveBox && !transcriptionAiLiveBox.hidden);
        const label = hasActiveTranscriptionAiRewrite()
            ? "Зупинити AI-обробку, повернути початковий текст і закрити панель"
            : "Закрити живий хід AI";

        transcriptionAiLiveCloseButton.hidden = !isVisible;
        transcriptionAiLiveCloseButton.disabled = !isVisible;
        transcriptionAiLiveCloseButton.setAttribute("aria-label", label);
        transcriptionAiLiveCloseButton.setAttribute("title", label);
    }

    function syncTranscriptionAiInputCloseButton() {
        if (!transcriptionAiInputCloseButton) {
            return;
        }

        const isVisible = Boolean(transcriptionAiInputBox && !transcriptionAiInputBox.hidden);
        const label = hasActiveTranscriptionAiRewrite()
            ? "Зупинити AI-обробку, повернути початковий текст і закрити формат запиту"
            : "Закрити формат запиту до AI";

        transcriptionAiInputCloseButton.hidden = !isVisible;
        transcriptionAiInputCloseButton.disabled = !isVisible;
        transcriptionAiInputCloseButton.setAttribute("aria-label", label);
        transcriptionAiInputCloseButton.setAttribute("title", label);
    }

    function showTranscriptionAiLiveBox() {
        if (transcriptionAiLiveBox) {
            transcriptionAiLiveBox.hidden = false;
        }

        syncTranscriptionAiLiveCloseButton();
    }

    function hideTranscriptionAiLiveBox() {
        if (transcriptionAiLiveBox) {
            transcriptionAiLiveBox.hidden = true;
        }

        syncTranscriptionAiLiveCloseButton();
    }

    function showTranscriptionAiInputBox() {
        if (transcriptionAiInputBox) {
            transcriptionAiInputBox.hidden = false;
        }

        syncTranscriptionAiInputCloseButton();
    }

    function hideTranscriptionAiInputBox() {
        if (transcriptionAiInputBox) {
            transcriptionAiInputBox.hidden = true;
        }

        syncTranscriptionAiInputCloseButton();
    }

    function resetTranscriptionAiInputState() {
        if (transcriptionAiInputStatus) {
            transcriptionAiInputStatus.textContent = "Запит";
            transcriptionAiInputStatus.classList.remove("is-pending", "is-running", "is-completed", "is-failed");
            transcriptionAiInputStatus.classList.add("is-pending");
        }

        if (transcriptionAiInputPhase) {
            transcriptionAiInputPhase.textContent = "Після запуску тут буде видно payload і prompt, які передаються в AI.";
        }

        if (transcriptionAiInputText) {
            transcriptionAiInputText.textContent = "Після запуску AI тут з'явиться формат запиту.";
            transcriptionAiInputText.scrollTop = 0;
        }
    }

    function renderTranscriptionAiInputPreview(previewPayload = {}) {
        showTranscriptionAiInputBox();

        if (transcriptionAiInputStatus) {
            transcriptionAiInputStatus.textContent = "Підготовлено";
            transcriptionAiInputStatus.classList.remove("is-pending", "is-running", "is-completed", "is-failed");
            transcriptionAiInputStatus.classList.add("is-running");
        }

        if (transcriptionAiInputPhase) {
            transcriptionAiInputPhase.textContent = "Нижче точний формат: що браузер відправляє в backend і який prompt backend збирає для Ollama.";
        }

        if (transcriptionAiInputText) {
            transcriptionAiInputText.textContent = JSON.stringify(previewPayload, null, 2);
            transcriptionAiInputText.scrollTop = 0;
        }

        syncTranscriptionAiInputCloseButton();
    }

    function renderTranscriptionAiLiveState(status = "pending", phaseText = "", { stickToBottom = false } = {}) {
        if (transcriptionAiLiveStatus) {
            transcriptionAiLiveStatus.textContent = transcriptionAiLiveStatusLabel(status);
            transcriptionAiLiveStatus.classList.remove("is-pending", "is-running", "is-completed", "is-failed");
            transcriptionAiLiveStatus.classList.add(transcriptionAiLiveStatusTone(status));
        }

        if (transcriptionAiInputStatus && transcriptionAiInputBox && !transcriptionAiInputBox.hidden) {
            transcriptionAiInputStatus.textContent = status === "running"
                ? "Відправлено"
                : transcriptionAiLiveStatusLabel(status);
            transcriptionAiInputStatus.classList.remove("is-pending", "is-running", "is-completed", "is-failed");
            transcriptionAiInputStatus.classList.add(transcriptionAiLiveStatusTone(status));
        }

        if (transcriptionAiLivePhase) {
            transcriptionAiLivePhase.textContent = phaseText || "Після запуску тут буде видно поточний прогрес AI-обробки та список виправлень.";
        }

        if (transcriptionAiLiveText) {
            const shouldStickToBottom = stickToBottom || Math.abs(
                (transcriptionAiLiveText.scrollHeight - transcriptionAiLiveText.clientHeight) - transcriptionAiLiveText.scrollTop
            ) < 24;

            transcriptionAiLiveText.textContent = buildTranscriptionAiLivePreviewText();

            if (shouldStickToBottom) {
                transcriptionAiLiveText.scrollTop = transcriptionAiLiveText.scrollHeight;
            }
        }

        syncTranscriptionAiLiveCloseButton();
        syncTranscriptionAiInputCloseButton();
    }

    function resetTranscriptionAiLiveState(
        phaseText = "Після запуску тут буде видно поточний прогрес AI-обробки та список виправлень.",
        status = "pending"
    ) {
        transcriptionAiThinkingText = "";
        transcriptionAiResponseText = "";
        renderTranscriptionAiLiveState(status, phaseText);
    }

    function syncTranscriptionAiRewriteControls() {
        const hasText = Boolean(transcriptionResultText?.value?.trim());
        const hasBlockingTask = Boolean(
            activeTranscriptionTaskId
            || activeTranscriptionRequestController
        );
        const hasActiveAiRewrite = hasActiveTranscriptionAiRewrite();
        const disableAiAction = hasActiveAiRewrite || hasBlockingTask || !hasText || !transcriptionAiRewriteEndpoint;

        if (transcriptionAiRewriteButton) {
            transcriptionAiRewriteButton.disabled = disableAiAction;
        }

        if (transcriptionAiSettingsButton) {
            transcriptionAiSettingsButton.disabled = hasActiveAiRewrite;
        }

        if (transcriptionAiStopButton) {
            transcriptionAiStopButton.hidden = !hasActiveAiRewrite;
            transcriptionAiStopButton.disabled = !hasActiveAiRewrite;
        }

        if (transcriptionAiSettingsSaveButton) {
            transcriptionAiSettingsSaveButton.disabled = hasActiveAiRewrite;
        }

        if (transcriptionResultText) {
            transcriptionResultText.readOnly = hasActiveAiRewrite;
        }

        syncTranscriptionAiLiveCloseButton();
        syncTranscriptionAiInputCloseButton();
    }

    function syncTranscriptionLlmControls() {
        const hasText = Boolean(transcriptionResultText?.value?.trim());
        const hasBlockingTask = Boolean(
            activeTranscriptionTaskId
            || activeTranscriptionRequestController
            || activeEvaluationRequestController
            || activeEvaluationPollController
            || activeEvaluationJobId
        );
        const evaluationEnabled = Boolean(transcriptionEvaluate?.checked);
        const disableLlmAction = hasBlockingTask || !hasText || !evaluationEnabled || !transcriptionEvaluationEndpoint;

        if (transcriptionLlmEvaluateButton) {
            transcriptionLlmEvaluateButton.disabled = disableLlmAction;
        }

        if (transcriptionLlmSettingsButton) {
            transcriptionLlmSettingsButton.disabled = hasBlockingTask;
        }

        if (transcriptionLlmSettingsSaveButton) {
            transcriptionLlmSettingsSaveButton.disabled = hasBlockingTask;
        }
    }

    function resolveExistingChecklistId(...candidates) {
        for (const candidate of candidates) {
            const normalized = typeof candidate === "string" ? candidate.trim() : "";
            if (normalized !== "" && checklists.some((checklist) => checklist.id === normalized)) {
                return normalized;
            }
        }

        return null;
    }

    function replaceObjectContents(target, source = {}) {
        Object.keys(target || {}).forEach((key) => {
            if (!Object.prototype.hasOwnProperty.call(source, key)) {
                delete target[key];
            }
        });

        Object.assign(target, source);
    }

    function syncSelectOptions(selectElement, options, preferredValue = "") {
        if (!selectElement) {
            return;
        }

        const nextOptions = [...new Set(
            (Array.isArray(options) ? options : [])
                .filter((value) => typeof value === "string" && value.trim() !== "")
        )];

        if (preferredValue && !nextOptions.includes(preferredValue)) {
            nextOptions.push(preferredValue);
        }

        selectElement.innerHTML = nextOptions.map((value) => `
            <option value="${escapeAttribute(value)}">${escapeHtml(value)}</option>
        `).join("");

        if (nextOptions.includes(preferredValue)) {
            selectElement.value = preferredValue;
            return;
        }

        selectElement.value = nextOptions[0] || "";
    }

    function syncCallsState({ preserveCustomRange = true } = {}) {
        hasCalls = calls.length > 0;

        if (!hasCalls) {
            if (!preserveCustomRange || !hasCustomDateRange) {
                rangeStart = null;
                rangeEnd = null;
            }

            draftRangeStart = null;
            draftRangeEnd = null;
            calendarViewDate = null;
            return;
        }

        const allCallDates = calls
            .map((call) => normalizeDate(parseDateOnly(call.date)))
            .sort((left, right) => left.getTime() - right.getTime());
        const nextStart = allCallDates[0];
        const nextEnd = allCallDates[allCallDates.length - 1];

        if (!preserveCustomRange || !hasCustomDateRange || !rangeStart || !rangeEnd) {
            rangeStart = nextStart;
            rangeEnd = nextEnd;
        }

        if (!calendarViewDate) {
            const seedDate = rangeStart || nextStart;
            calendarViewDate = new Date(seedDate.getFullYear(), seedDate.getMonth(), 1);
        }
    }

    function hasChecklistRefreshLock() {
        return isChecklistEditorDirty
            || Boolean(activeChecklistRenameId)
            || isChecklistRenameSaving
            || !checklistDeleteModal.hidden
            || isChecklistDeleteSubmitting;
    }

    function selectedWhisperModel() {
        return settingsWhisperModel?.value
            || transcriptionSettings?.transcription_model
            || "large-v3";
    }

    function speakerDiarizationEnabled() {
        return Boolean(
            settingsSpeakerDiarizationEnabled?.checked
            ?? transcriptionSettings?.speaker_diarization_enabled
            ?? false
        );
    }

    function hasStoredSpeakerDiarizationToken() {
        return Boolean(transcriptionSettings?.speaker_diarization_has_token);
    }

    function hasSpeakerDiarizationToken() {
        return Boolean(settingsSpeakerDiarizationToken?.value?.trim()) || hasStoredSpeakerDiarizationToken();
    }

    function normalizeWhisperInitialPromptPasteText(value) {
        return String(value || "")
            .replace(/\r\n?/g, "\n")
            .replace(/\u00a0/g, " ")
            .replace(/\t+/g, "\n")
            .replace(/[ \f\v]{3,}/g, "\n")
            .split("\n")
            .map((line) => line.replace(/[^\S\n]+/g, " ").trim())
            .filter(Boolean)
            .join("\n");
    }

    function insertTextareaText(textarea, text) {
        const start = Number.isFinite(textarea.selectionStart) ? textarea.selectionStart : textarea.value.length;
        const end = Number.isFinite(textarea.selectionEnd) ? textarea.selectionEnd : start;

        textarea.value = `${textarea.value.slice(0, start)}${text}${textarea.value.slice(end)}`;

        const nextCursorPosition = start + text.length;
        textarea.setSelectionRange(nextCursorPosition, nextCursorPosition);
        textarea.dispatchEvent(new Event("input", { bubbles: true }));
    }

    function handleWhisperInitialPromptPaste(event) {
        const pastedText = event.clipboardData?.getData("text/plain") || "";
        const normalizedText = normalizeWhisperInitialPromptPasteText(pastedText);

        if (normalizedText === "" || normalizedText === pastedText) {
            return;
        }

        event.preventDefault();
        insertTextareaText(event.currentTarget, normalizedText);
    }

    function formatFileSize(bytes) {
        if (!Number.isFinite(bytes) || bytes <= 0) {
            return "0 Б";
        }

        if (bytes >= 1024 * 1024) {
            return `${(bytes / (1024 * 1024)).toFixed(1)} МБ`;
        }

        return `${Math.max(1, Math.round(bytes / 1024))} КБ`;
    }

    function looksLikeBinotelCabinetUrl(value) {
        try {
            const url = new URL(value);
            const host = url.hostname.toLowerCase();
            const path = url.pathname.toLowerCase();

            return host.includes("binotel.ua") && (value.includes("#/") || path.startsWith("/f/pbx"));
        } catch (error) {
            return false;
        }
    }

    function parseDateTime(call) {
        const [day, month, year] = call.date.split(".").map(Number);
        const [hours, minutes] = call.time.split(":").map(Number);
        return new Date(year, month - 1, day, hours, minutes).getTime();
    }

    function formatDate(date) {
        const day = String(date.getDate()).padStart(2, "0");
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const year = date.getFullYear();
        return `${day}.${month}.${year}`;
    }

    function normalizeDate(date) {
        return new Date(date.getFullYear(), date.getMonth(), date.getDate());
    }

    function isSameDate(left, right) {
        return left && right
            && left.getFullYear() === right.getFullYear()
            && left.getMonth() === right.getMonth()
            && left.getDate() === right.getDate();
    }

    function getFilteredCalls() {
        return calls.filter((call) => {
            const searchValue = normalizePhone(phoneSearchValue);
            const employeeMatch = employeeFilterValue === "all" || call.employee === employeeFilterValue;
            const numberMatch = !searchValue || [
                call.caller,
                call.callerMeta,
                call.employeeMeta
            ].some((value) => normalizePhone(value).includes(searchValue));
            const callDate = parseDateOnly(call.date);
            const startMatch = !rangeStart || callDate >= rangeStart;
            const endMatch = !rangeEnd || callDate <= rangeEnd;
            const dateMatch = startMatch && endMatch;
            return employeeMatch && numberMatch && dateMatch;
        });
    }

    function getProcessedCalls() {
        const filtered = getFilteredCalls();

        filtered.sort((left, right) => {
            let leftValue;
            let rightValue;

            if (sortField === "model") {
                leftValue = callModelSortWeight(left);
                rightValue = callModelSortWeight(right);
            } else if (sortField === "duration") {
                leftValue = parseDurationToSeconds(left.duration);
                rightValue = parseDurationToSeconds(right.duration);
            } else if (sortField === "interactionCount") {
                leftValue = interactionCountForCall(left) || 0;
                rightValue = interactionCountForCall(right) || 0;
            } else if (sortField === "interactionNumber") {
                leftValue = interactionNumberForCall(left) || 0;
                rightValue = interactionNumberForCall(right) || 0;
            } else if (sortField === "score") {
                leftValue = left.score;
                rightValue = right.score;
            } else {
                leftValue = parseDateTime(left);
                rightValue = parseDateTime(right);
            }

            if (leftValue === rightValue) {
                if (sortField === "model") {
                    return compareText(callModelLabel(left), callModelLabel(right), sortDirection);
                }

                return 0;
            }

            if (sortDirection === "asc") {
                return leftValue - rightValue;
            }

            return rightValue - leftValue;
        });

        return filtered;
    }

    function callModelLabel(call) {
        return String(call.model ?? "").trim();
    }

    function callModelMetaLabel(call) {
        return String(call.modelMeta ?? "").trim();
    }

    function callModelSortWeight(call) {
        const numericValue = Number(call.modelSortValue);

        if (Number.isFinite(numericValue)) {
            return numericValue;
        }

        const match = callModelLabel(call).match(/(\d+(?:\.\d+)?)\s*b\b/i);

        if (!match) {
            return -1;
        }

        return Number(match[1]);
    }

    function compareText(left, right, direction = "asc") {
        const result = String(left || "").localeCompare(String(right || ""), "uk", {
            numeric: true,
            sensitivity: "base"
        });

        return direction === "asc" ? result : -result;
    }

    function getRangeFilteredCalls() {
        return calls.filter((call) => {
            const callDate = parseDateOnly(call.date);
            const startMatch = !rangeStart || callDate >= rangeStart;
            const endMatch = !rangeEnd || callDate <= rangeEnd;
            return startMatch && endMatch;
        });
    }

    function paginateRows(items, currentPage, perPage) {
        const totalPages = Math.max(1, Math.ceil(items.length / perPage));
        const safePage = Math.min(Math.max(currentPage, 1), totalPages);
        const startIndex = (safePage - 1) * perPage;
        const endIndex = startIndex + perPage;

        return {
            pageItems: items.slice(startIndex, endIndex),
            totalPages,
            currentPage: safePage,
            startIndex
        };
    }

    function buildPaginationSequence(currentPage, totalPages) {
        const edgeWindowSize = 12;
        const middleRadius = 3;

        if (totalPages <= edgeWindowSize + 2) {
            return Array.from({ length: totalPages }, (_, index) => index + 1);
        }

        if (currentPage <= edgeWindowSize - 1) {
            return [
                ...Array.from({ length: edgeWindowSize }, (_, index) => index + 1),
                "ellipsis",
                totalPages,
            ];
        }

        if (currentPage >= totalPages - edgeWindowSize + 2) {
            return [
                1,
                "ellipsis",
                ...Array.from({ length: edgeWindowSize }, (_, index) => totalPages - edgeWindowSize + 1 + index),
            ];
        }

        let start = Math.max(2, currentPage - middleRadius);
        let end = Math.min(totalPages - 1, currentPage + middleRadius);

        while ((end - start) < (middleRadius * 2)) {
            if (start > 2) {
                start -= 1;
                continue;
            }

            if (end < totalPages - 1) {
                end += 1;
                continue;
            }

            break;
        }

        const middlePages = Array.from(
            { length: Math.max(0, end - start + 1) },
            (_, index) => start + index,
        );

        return [1, "ellipsis", ...middlePages, "ellipsis", totalPages];
    }

    function renderPagination(container, page, totalPages, totalItems, perPage, itemLabel) {
        if (!container) {
            return;
        }

        if (totalItems === 0) {
            container.hidden = true;
            container.innerHTML = "";
            return;
        }

        const safePage = Math.min(Math.max(page, 1), totalPages);
        const startItem = ((safePage - 1) * perPage) + 1;
        const endItem = Math.min(totalItems, safePage * perPage);
        const pageButtons = buildPaginationSequence(safePage, totalPages).map((item, index) => {
            if (item === "ellipsis") {
                return `<span class="pagination-ellipsis" aria-hidden="true">…</span>`;
            }

            const pageNumber = Number(item);
            const activeClass = pageNumber === safePage ? "active" : "";

            return `
                <button
                    type="button"
                    class="pagination-button ${activeClass}"
                    data-page="${pageNumber}"
                    aria-label="Сторінка ${pageNumber}"
                    ${pageNumber === safePage ? 'aria-current="page"' : ""}
                >${pageNumber}</button>
            `;
        }).join("");

        container.hidden = false;
        container.innerHTML = `
            <div class="table-pagination-controls">
                <button type="button" class="pagination-button" data-page="${safePage - 1}" ${safePage === 1 ? "disabled" : ""}>←</button>
                ${pageButtons}
                <button type="button" class="pagination-button" data-page="${safePage + 1}" ${safePage === totalPages ? "disabled" : ""}>→</button>
            </div>
            <div class="table-pagination-summary">Показано ${startItem}-${endItem} з ${totalItems} ${itemLabel}</div>
        `;
    }

    function syncEmployeeFilterDropdown() {
        const selectedOption = employeeFilter.options[employeeFilter.selectedIndex];
        employeeFilterText.textContent = selectedOption ? selectedOption.textContent : "Всі менеджери";

        employeeFilterDropdown.innerHTML = [...employeeFilter.options].map((option) => `
            <button
                type="button"
                class="filter-select-option ${option.value === employeeFilterValue ? "active" : ""}"
                data-value="${escapeAttribute(option.value)}"
                role="option"
                aria-selected="${option.value === employeeFilterValue ? "true" : "false"}"
            >${escapeHtml(option.textContent)}</button>
        `).join("");
    }

    function closeEmployeeFilterDropdown() {
        employeeFilterDropdown.hidden = true;
        employeeFilterBackdrop.hidden = true;
        employeeFilterField.classList.remove("open");
        employeeFilterTrigger.setAttribute("aria-expanded", "false");
    }

    function openEmployeeFilterDropdown() {
        employeeFilterDropdown.hidden = false;
        employeeFilterBackdrop.hidden = false;
        employeeFilterField.classList.add("open");
        employeeFilterTrigger.setAttribute("aria-expanded", "true");
    }

    function renderFilterOptions() {
        const employees = [...new Set(calls.map((call) => call.employee))];
        const nextEmployeeFilterValue = employees.includes(employeeFilterValue) ? employeeFilterValue : "all";

        employeeFilterValue = nextEmployeeFilterValue;

        employeeFilter.innerHTML = [
            `<option value="all">Всі менеджери</option>`,
            ...employees.map((employee) => `<option value="${escapeHtml(employee)}">${escapeHtml(employee)}</option>`)
        ].join("");

        employeeFilter.value = nextEmployeeFilterValue;
        syncEmployeeFilterDropdown();
    }

    function renderSortState() {
        [interactionCountSort, interactionNumberSort, modelSort, durationSort, timeSort, scoreSort].forEach((button) => {
            button.classList.remove("active", "asc", "desc");
        });

        const activeButton = {
            duration: durationSort,
            interactionCount: interactionCountSort,
            interactionNumber: interactionNumberSort,
            model: modelSort,
            score: scoreSort,
            time: timeSort
        }[sortField] || timeSort;

        activeButton.classList.add("active", sortDirection);
    }

    function renderDateRangeSummary() {
        const startText = rangeStart ? formatDate(rangeStart) : "Усі дати";
        const endText = rangeEnd ? formatDate(rangeEnd) : startText;
        const label = rangeStart && rangeEnd ? `${startText} - ${endText}` : startText;

        dateRangeText.textContent = label;
        activeDateLabel.textContent = label;
        if (managersDateRangeText) {
            managersDateRangeText.textContent = label;
        }
    }

    function renderDateInputs() {
        dateStartInput.value = draftRangeStart ? formatDate(draftRangeStart) : "";
        dateEndInput.value = draftRangeEnd ? formatDate(draftRangeEnd) : (draftRangeStart ? formatDate(draftRangeStart) : "");
    }

    function renderWeekdays(target) {
        target.innerHTML = weekdayLabels.map((label) => `<div class="weekday">${label}</div>`).join("");
    }

    function buildMonthGrid(target, monthDate) {
        const year = monthDate.getFullYear();
        const month = monthDate.getMonth();
        const firstDay = new Date(year, month, 1);
        const firstWeekday = (firstDay.getDay() + 6) % 7;
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const draftStartTime = draftRangeStart ? draftRangeStart.getTime() : null;
        const draftEndTime = draftRangeEnd ? draftRangeEnd.getTime() : null;
        const rangeStartTime = draftStartTime !== null ? Math.min(draftStartTime, draftEndTime ?? draftStartTime) : null;
        const rangeEndTime = draftStartTime !== null ? Math.max(draftStartTime, draftEndTime ?? draftStartTime) : null;

        const cells = [];
        for (let index = 0; index < firstWeekday; index += 1) {
            cells.push('<span class="calendar-empty"></span>');
        }

        for (let day = 1; day <= daysInMonth; day += 1) {
            const date = new Date(year, month, day);
            const time = date.getTime();
            const classes = ["calendar-day"];

            if (rangeStartTime !== null && rangeEndTime !== null && time >= rangeStartTime && time <= rangeEndTime) {
                classes.push("in-range");
            }

            if (draftRangeStart && isSameDate(date, draftRangeStart)) {
                classes.push("range-start");
            }

            if (draftRangeEnd && isSameDate(date, draftRangeEnd)) {
                classes.push("range-end");
            }

            cells.push(`
                <button
                    type="button"
                    class="${classes.join(" ")}"
                    data-calendar-date="${formatDate(date)}"
                >${day}</button>
            `);
        }

        target.innerHTML = cells.join("");
    }

    function renderCalendar() {
        const secondMonth = new Date(calendarViewDate.getFullYear(), calendarViewDate.getMonth() + 1, 1);

        monthTitleA.textContent = `${monthLabels[calendarViewDate.getMonth()]} ${calendarViewDate.getFullYear()}`;
        monthTitleB.textContent = `${monthLabels[secondMonth.getMonth()]} ${secondMonth.getFullYear()}`;

        renderWeekdays(weekdaysA);
        renderWeekdays(weekdaysB);
        buildMonthGrid(monthGridA, calendarViewDate);
        buildMonthGrid(monthGridB, secondMonth);
        renderDateInputs();
    }

    function openDatePicker() {
        if (!hasCalls) {
            return;
        }

        draftRangeStart = rangeStart ? new Date(rangeStart) : null;
        draftRangeEnd = rangeEnd ? new Date(rangeEnd) : null;
        calendarViewDate = new Date((draftRangeStart || rangeStart || parseDateOnly(calls[0].date)).getFullYear(), (draftRangeStart || rangeStart || parseDateOnly(calls[0].date)).getMonth(), 1);
        renderCalendar();
        datePicker.hidden = false;
    }

    function closeDatePicker() {
        datePicker.hidden = true;
    }

    function handleCalendarDateSelect(value) {
        const clickedDate = normalizeDate(parseDateOnly(value));

        if (!draftRangeStart || (draftRangeStart && draftRangeEnd)) {
            draftRangeStart = clickedDate;
            draftRangeEnd = null;
        } else if (clickedDate.getTime() < draftRangeStart.getTime()) {
            draftRangeStart = clickedDate;
        } else {
            draftRangeEnd = clickedDate;
        }

        renderCalendar();
    }

    function renderRows() {
        const rows = getProcessedCalls();
        const paginationState = paginateRows(rows, callsPage, callsPerPage);
        const pageRows = paginationState.pageItems;
        callsPage = paginationState.currentPage;

        if (!pageRows.some((call) => call.id === selectedCallId)) {
            selectedCallId = pageRows[0]?.id ?? rows[0]?.id ?? null;
        }

        callsCount.textContent = `${rows.length} дзвінків`;
        renderDateRangeSummary();
        renderSortState();

        if (rows.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="11" style="text-align:center; padding: 28px 16px; color: #7c8398;">
                        За вибраними фільтрами дзвінків нічого не знайдено.
                    </td>
                </tr>
            `;
            renderPagination(callsPagination, 1, 1, 0, callsPerPage, "дзвінків");
            return;
        }

        tableBody.innerHTML = pageRows.map((call) => {
            const activeClass = call.id === selectedCallId ? "active" : "";
            const directionClass = call.direction === "in" ? "dir-in" : "dir-out";
            const directionLabel = call.direction === "in" ? "Вхідний дзвінок" : "Вихідний дзвінок";
            const interactionCount = interactionCountForCall(call);
            const interactionNumber = interactionNumberForCall(call);
            const interactionCountLabel = interactionCount === null ? "—" : String(interactionCount);
            const interactionNumberLabel = interactionNumber === null ? "—" : String(interactionNumber);
            const interactionCountTitle = interactionCount === null
                ? "Кількість взаємодій менеджера з цим номером"
                : interactionCount > 1
                    ? "Відкрити історію взаємодій"
                    : "Одна взаємодія менеджера з цим номером";
            const interactionNumberTitle = interactionNumber === null
                ? "Порядковий номер взаємодії менеджера з цим номером"
                : interactionCount > 1
                    ? `Взаємодія ${interactionNumber} з ${interactionCount}. Відкрити історію`
                    : "Перша взаємодія менеджера з цим номером";
            const interactionCountBadge = interactionCount > 1 && !isInteractionHistoryMode
                ? `<a class="interaction-count-badge" href="${escapeAttribute(buildInteractionHistoryUrl(call))}" target="_blank" data-interaction-history-link="true" aria-label="Відкрити історію взаємодій" title="${escapeAttribute(interactionCountTitle)}">${escapeHtml(interactionCountLabel)}</a>`
                : `<span class="interaction-count-badge" data-interaction-count-static="true" title="${escapeAttribute(interactionCountTitle)}">${escapeHtml(interactionCountLabel)}</span>`;
            const interactionNumberBadge = interactionCount > 1 && !isInteractionHistoryMode
                ? `<a class="interaction-count-badge" href="${escapeAttribute(buildInteractionHistoryUrl(call))}" target="_blank" data-interaction-history-link="true" aria-label="Відкрити історію взаємодій" title="${escapeAttribute(interactionNumberTitle)}">${escapeHtml(interactionNumberLabel)}</a>`
                : `<span class="interaction-count-badge" data-interaction-count-static="true" title="${escapeAttribute(interactionNumberTitle)}">${escapeHtml(interactionNumberLabel)}</span>`;
            const modelLabel = callModelLabel(call) || "—";
            const modelMeta = callModelMetaLabel(call);
            const employeeMeta = /\d+\s*>\s*\d+/.test(String(call.employeeMeta ?? ""))
                ? ""
                : String(call.employeeMeta ?? "");

            return `
                <tr class="${activeClass}" data-call-id="${call.id}">
                    <td class="dir-cell"><span class="dir-indicator ${directionClass}" role="img" aria-label="${directionLabel}" title="${directionLabel}"></span></td>
                    <td class="interaction-count-cell">
                        ${interactionCountBadge}
                    </td>
                    <td class="interaction-number-cell">
                        ${interactionNumberBadge}
                    </td>
                    <td class="caller-cell">
                        <div class="main-text">${escapeHtml(call.caller)}</div>
                    </td>
                    <td class="model-cell">
                        <div class="main-text">${escapeHtml(modelLabel)}</div>
                        ${modelMeta ? `<span class="sub-text">${escapeHtml(modelMeta)}</span>` : ""}
                    </td>
                    <td class="employee-cell">
                        <div class="main-text">${escapeHtml(call.employee)}</div>
                        ${employeeMeta ? `<span class="sub-text">${escapeHtml(employeeMeta)}</span>` : ""}
                    </td>
                    <td class="score-cell">
                        <button type="button" class="score-chip ${scoreClass(call.score)}" data-action="score" aria-label="Відкрити оцінку" title="Оцінка">
                            ${escapeHtml(displayScore(call.score))}
                        </button>
                    </td>
                    <td class="duration-cell">${escapeHtml(call.duration)}</td>
                    <td class="time-cell">
                        <span class="time-main">${escapeHtml(call.time)}</span>
                        <span class="time-sub">${escapeHtml(call.date)}</span>
                    </td>
                    <td class="action-cell">
                        <button type="button" class="icon-button" data-action="transcript" aria-label="Відкрити транскрибацію" title="Транскрибація">
                            <span class="bubble-icon"></span>
                        </button>
                    </td>
                    <td class="action-cell">
                        <button type="button" class="icon-button" data-action="audio" aria-label="Відкрити аудіо" title="Аудіо">
                            <span class="sound-icon"></span>
                        </button>
                    </td>
                </tr>
            `;
        }).join("");

        renderPagination(callsPagination, callsPage, paginationState.totalPages, rows.length, callsPerPage, "дзвінків");
    }

    function renderManagersRows() {
        const rows = buildManagersFromCalls(getRangeFilteredCalls());
        const paginationState = paginateRows(rows, managersPage, managersPerPage);
        const pageRows = paginationState.pageItems;
        managersPage = paginationState.currentPage;

        if (rows.length === 0) {
            managersTableBody.innerHTML = `
                <tr>
                    <td colspan="4" style="text-align:center; padding: 28px 16px; color: #7c8398;">
                        За вибраним періодом менеджерів поки не знайдено.
                    </td>
                </tr>
            `;
            renderPagination(managersPagination, 1, 1, 0, managersPerPage, "менеджерів");
            return;
        }

        managersTableBody.innerHTML = pageRows.map((manager) => `
            <tr>
                <td class="managers-name-col"><span class="main-text">${escapeHtml(manager.name)}</span></td>
                <td class="managers-count-col">${escapeHtml(manager.callsCount)}</td>
                <td class="managers-score-col"><span class="score-chip ${scoreClass(manager.score)} managers-score-chip">${escapeHtml(displayScore(manager.score))}</span></td>
                <td class="managers-recommendation">${escapeHtml(manager.recommendation)}</td>
            </tr>
        `).join("");

        renderPagination(managersPagination, managersPage, paginationState.totalPages, rows.length, managersPerPage, "менеджерів");
    }

    function readStoredCallsTableColumnWidths() {
        try {
            const parsed = JSON.parse(localStorage.getItem(callsTableColumnStorageKey) || "null");
            if (!parsed || typeof parsed !== "object") {
                return null;
            }

            const widths = callsTableColumnConfig.reduce((nextWidths, column) => {
                const width = Number(parsed[column.id]);

                if (Number.isFinite(width)) {
                    nextWidths[column.id] = Math.max(column.minWidth, Math.min(1200, Math.round(width)));
                }

                return nextWidths;
            }, {});

            return callsTableColumnConfig.every((column) => Number.isFinite(widths[column.id]))
                ? widths
                : null;
        } catch (error) {
            return null;
        }
    }

    function writeStoredCallsTableColumnWidths(widths) {
        try {
            localStorage.setItem(callsTableColumnStorageKey, JSON.stringify(widths));
        } catch (error) {
            // Browser storage can be unavailable in private mode; resizing still works for this session.
        }
    }

    function getCallsTableColumnWidths() {
        if (!callsTable) {
            return {};
        }

        return callsTableColumnConfig.reduce((widths, column) => {
            const col = callsTable.querySelector(`col[data-call-column="${column.id}"]`);
            const heading = callsTable.querySelector(`th[data-call-column="${column.id}"]`);
            const styleWidth = Number.parseFloat(col?.style.width || "");
            const measuredWidth = heading?.getBoundingClientRect().width || 0;
            const width = Number.isFinite(styleWidth) && styleWidth > 0 ? styleWidth : measuredWidth;

            widths[column.id] = Math.max(column.minWidth, Math.round(width || column.minWidth));

            return widths;
        }, {});
    }

    function applyCallsTableColumnWidths(widths, { persist = false } = {}) {
        if (!callsTable) {
            return;
        }

        const normalizedWidths = {};
        const totalWidth = callsTableColumnConfig.reduce((sum, column) => {
            const width = Math.max(column.minWidth, Math.round(Number(widths[column.id]) || column.minWidth));
            const col = callsTable.querySelector(`col[data-call-column="${column.id}"]`);

            if (col) {
                col.style.width = `${width}px`;
            }

            normalizedWidths[column.id] = width;

            return sum + width;
        }, 0);

        callsTable.style.width = `${totalWidth}px`;
        callsTable.style.minWidth = `${totalWidth}px`;

        if (persist) {
            writeStoredCallsTableColumnWidths(normalizedWidths);
        }
    }

    function resetCallsTableColumnWidths() {
        if (!callsTable) {
            return;
        }

        callsTable.querySelectorAll("col[data-call-column]").forEach((col) => {
            col.style.width = "";
        });
        callsTable.style.width = "";
        callsTable.style.minWidth = "";

        try {
            localStorage.removeItem(callsTableColumnStorageKey);
        } catch (error) {
            // Ignore unavailable browser storage.
        }
    }

    function initializeCallsTableColumnWidths() {
        const storedWidths = readStoredCallsTableColumnWidths();

        if (storedWidths && Object.keys(storedWidths).length > 0) {
            applyCallsTableColumnWidths(storedWidths);
        }
    }

    function stopCallsTableColumnResize({ persist = true } = {}) {
        if (!activeColumnResize) {
            return;
        }

        if (persist) {
            writeStoredCallsTableColumnWidths(getCallsTableColumnWidths());
        }

        activeColumnResize = null;
        document.body.classList.remove("is-resizing-columns");
        callsTable?.classList.remove("is-resizing");
        document.removeEventListener("pointermove", handleCallsTableColumnResize);
        document.removeEventListener("pointerup", handleCallsTableColumnResizeEnd);
        document.removeEventListener("pointercancel", handleCallsTableColumnResizeCancel);
    }

    function handleCallsTableColumnResize(event) {
        if (!activeColumnResize) {
            return;
        }

        const { column, nextColumn, startX, startWidths } = activeColumnResize;
        const leftConfig = callsTableColumnConfig.find((item) => item.id === column);
        const rightConfig = callsTableColumnConfig.find((item) => item.id === nextColumn);

        if (!leftConfig || !rightConfig) {
            stopCallsTableColumnResize({ persist: false });
            return;
        }

        const leftStart = startWidths[column];
        const rightStart = startWidths[nextColumn];
        const minDelta = leftConfig.minWidth - leftStart;
        const maxDelta = rightStart - rightConfig.minWidth;
        const delta = Math.max(minDelta, Math.min(maxDelta, event.clientX - startX));
        const nextWidths = {
            ...startWidths,
            [column]: Math.round(leftStart + delta),
            [nextColumn]: Math.round(rightStart - delta),
        };

        applyCallsTableColumnWidths(nextWidths);
    }

    function handleCallsTableColumnResizeEnd() {
        stopCallsTableColumnResize({ persist: true });
    }

    function handleCallsTableColumnResizeCancel() {
        stopCallsTableColumnResize({ persist: false });
    }

    function startCallsTableColumnResize(event) {
        const resizer = event.target.closest("[data-call-column-resizer]");
        if (!resizer || !callsTable || !callsTableWrap) {
            return;
        }

        const column = resizer.dataset.callColumnResizer;
        const columnIndex = callsTableColumnConfig.findIndex((item) => item.id === column);
        const nextColumn = callsTableColumnConfig[columnIndex + 1]?.id || "";

        if (columnIndex < 0 || nextColumn === "") {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        activeColumnResize = {
            column,
            nextColumn,
            startX: event.clientX,
            startWidths: getCallsTableColumnWidths(),
        };

        document.body.classList.add("is-resizing-columns");
        callsTable.classList.add("is-resizing");
        resizer.setPointerCapture?.(event.pointerId);
        document.addEventListener("pointermove", handleCallsTableColumnResize);
        document.addEventListener("pointerup", handleCallsTableColumnResizeEnd);
        document.addEventListener("pointercancel", handleCallsTableColumnResizeCancel);
    }

    function setPageScrollLocked(locked) {
        if (locked) {
            if (document.body.classList.contains("is-page-scroll-locked")) {
                return;
            }

            pageScrollLockY = window.scrollY || window.pageYOffset || 0;
            document.body.classList.add("is-page-scroll-locked");
            document.body.style.top = `-${pageScrollLockY}px`;
            return;
        }

        if (!document.body.classList.contains("is-page-scroll-locked")) {
            return;
        }

        document.body.classList.remove("is-page-scroll-locked");
        document.body.style.top = "";
        window.scrollTo(0, pageScrollLockY);
    }

    function syncBodyScrollLock() {
        setPageScrollLocked(
            !callModal.hidden
            || !checklistDeleteModal.hidden
            || !checklistExportModal.hidden
            || !transcriptionLlmSettingsModal.hidden
            || !transcriptionAiSettingsModal.hidden
        );
    }

    function openModal(kicker, title, subtitle, body) {
        modalKicker.textContent = kicker;
        modalTitle.textContent = title;
        modalSubtitle.textContent = subtitle;
        modalBody.innerHTML = body;
        callModal.hidden = false;
        syncBodyScrollLock();
    }

    function closeModal() {
        callModal.hidden = true;
        activeModalKind = null;
        activeModalCallId = null;
        syncBodyScrollLock();
    }

    function openTranscriptModal(call) {
        activeModalKind = "transcript";
        activeModalCallId = Number(call?.id) || null;
        openModal(
            "Транскрибація",
            `${call.caller} -> ${call.employee}`,
            `Статус: ${call.transcriptStatus}. Повний текст розмови за вибраним дзвінком.`,
            `
                <div class="modal-grid">
                    <section class="modal-box">
                        <h3>Текст розмови</h3>
                        <p class="transcript-text">${escapeHtml(call.transcript)}</p>
                    </section>
                    <section class="modal-box">
                        <h3>Метадані дзвінка</h3>
                        <p>Дата: ${escapeHtml(call.date)}<br>Час: ${escapeHtml(call.time)}<br>Тривалість: ${escapeHtml(call.duration)}</p>
                    </section>
                </div>
            `
        );
    }

    function openAudioModal(call, { refresh = true } = {}) {
        activeModalKind = "audio";
        activeModalCallId = Number(call?.id) || null;

        const callId = Number(call?.id);
        const shouldRefreshAudioUrl = refresh
            && Number.isFinite(callId)
            && !audioRefreshCallIds.has(callId)
            && callAudioRefreshEndpoint(callId) !== "";

        if (shouldRefreshAudioUrl) {
            audioRefreshCallIds.add(callId);
        }

        openModal(
            "Аудіо",
            `Запис дзвінка ${call.caller}`,
            `Статус: ${call.audioStatus}. Тут доступне прослуховування запису дзвінка.`,
            `
                <div class="modal-grid">
                    <section class="modal-box">
                        <h3>Запис дзвінка</h3>
                        ${renderAudioPlayer(call)}
                    </section>
                    <section class="modal-box">
                        <h3>Інформація про запис</h3>
                        <p>Клієнт: ${escapeHtml(call.caller)}<br>Співробітник: ${escapeHtml(call.employee)}<br>Тривалість: ${escapeHtml(call.duration)}<br>Коментар: ${escapeHtml(call.note)}</p>
                    </section>
                </div>
            `
        );

        if (shouldRefreshAudioUrl) {
            refreshCallAudioUrl(call);
        }
    }

    function renderScoreItems(call) {
        if (!Array.isArray(call.scoreItems) || call.scoreItems.length === 0) {
            return `
                <article class="score-item">
                    <div>
                        <h4>Оцінка ще не виконана</h4>
                        <p>Дзвінок вже завантажено з Binotel. Детальна AI-оцінка з'явиться після окремого аналізу.</p>
                    </div>
                    <div class="score-chip is-muted">—</div>
                </article>
            `;
        }

        const scoreItemValue = (item) => {
            const score = numericScoreValue(item?.score);
            const maxPoints = numericScoreValue(item?.maxPoints);

            if (score === null) {
                return "—";
            }

            return maxPoints === null ? displayScore(score) : `${displayScore(score)}/${displayScore(maxPoints)}`;
        };

        return call.scoreItems.map((item) => `
            <article class="score-item">
                <div>
                    <h4>${escapeHtml(item.title)}</h4>
                    <p>${escapeHtml(item.text)}</p>
                </div>
                <div class="score-chip ${scoreClass(item.percentage ?? item.score)}">${escapeHtml(scoreItemValue(item))}</div>
            </article>
        `).join("");
    }

    function renderScoreModelMeta(call) {
        const summary = String(call?.evaluationMeta?.summary || "").trim();

        if (summary === "") {
            return "";
        }

        return `<p class="score-meta">${escapeHtml(summary)}</p>`;
    }

    function openScoreModal(call) {
        activeModalKind = "score";
        activeModalCallId = Number(call?.id) || null;
        const modelTitle = String(call?.evaluationMeta?.title || "").trim();
        openModal(
            "Оцінка",
            `Підсумок за дзвінком: ${displayScore(call.score)}`,
            `Детальна оцінка розмови ${call.caller} -> ${call.employee}.${modelTitle ? ` Модель: ${modelTitle}.` : ""}`,
            `
                <div class="modal-grid">
                    <section class="modal-box">
                        <div class="score-summary">
                            <div class="score-circle">${escapeHtml(displayScore(call.score))}</div>
                            <div>
                                <h3>Загальний висновок</h3>
                                <p>${escapeHtml(call.summary)}</p>
                                ${renderScoreModelMeta(call)}
                            </div>
                        </div>
                    </section>
                    <section class="score-list">
                        ${renderScoreItems(call)}
                    </section>
                </div>
            `
        );
    }

    function reopenActiveCallModal() {
        if (callModal.hidden || !activeModalKind) {
            return;
        }

        const call = findCall(activeModalCallId ?? selectedCallId);
        if (!call) {
            closeModal();
            return;
        }

        if (activeModalKind === "transcript") {
            openTranscriptModal(call);
            return;
        }

        if (activeModalKind === "audio") {
            openAudioModal(call, { refresh: false });
            return;
        }

        openScoreModal(call);
    }

    function openChecklistDeleteModal(checklistId) {
        const checklist = findChecklist(checklistId);
        if (!checklist || !checklistDeleteModal || !checklistDeleteMessage) {
            return;
        }

        pendingChecklistDeleteId = checklistId;
        checklistDeleteMessage.textContent = `Ви впевнені, що хочете видалити чек-лист «${checklist.name}»?`;
        checklistDeleteConfirmButton.disabled = false;
        checklistDeleteCancelButton.disabled = false;
        checklistDeleteModal.hidden = false;
        syncBodyScrollLock();
        requestAnimationFrame(() => {
            checklistDeleteCancelButton?.focus();
        });
    }

    function closeChecklistDeleteModal({ keepPendingDeleteId = false } = {}) {
        if (!checklistDeleteModal) {
            return;
        }

        checklistDeleteModal.hidden = true;
        checklistDeleteConfirmButton.disabled = false;
        checklistDeleteCancelButton.disabled = false;
        isChecklistDeleteSubmitting = false;

        if (!keepPendingDeleteId) {
            pendingChecklistDeleteId = null;
        }

        syncBodyScrollLock();
    }

    function openChecklistExportModal() {
        if (!checklistExportModal) {
            return;
        }

        const payload = currentChecklistPayload();

        if (!Array.isArray(payload.items) || payload.items.length === 0) {
            setChecklistFeedback("Немає пунктів для експорту. Додайте хоча б один пункт чек-листа.", "is-error");
            return;
        }

        checklistExportModal.hidden = false;
        syncBodyScrollLock();
        requestAnimationFrame(() => {
            checklistExportChatGptButton?.focus();
        });
    }

    function closeChecklistExportModal() {
        if (!checklistExportModal) {
            return;
        }

        checklistExportModal.hidden = true;
        syncBodyScrollLock();
    }

    function openTranscriptionLlmSettingsModal({ focusPrompt = false } = {}) {
        if (!transcriptionLlmSettingsModal) {
            return;
        }

        transcriptionLlmSettingsDraft = normalizeTranscriptionLlmSettings(transcriptionLlmSettingsState);
        syncTranscriptionLlmSettingsForm(transcriptionLlmSettingsDraft);
        transcriptionLlmSettingsModal.hidden = false;
        syncBodyScrollLock();

        requestAnimationFrame(() => {
            if (focusPrompt) {
                transcriptionLlmSystemPrompt?.focus();
                return;
            }

            transcriptionLlmModel?.focus();
        });
    }

    function closeTranscriptionLlmSettingsModal() {
        if (!transcriptionLlmSettingsModal) {
            return;
        }

        transcriptionLlmSettingsModal.hidden = true;
        transcriptionLlmSettingsDraft = null;
        syncBodyScrollLock();
    }

    function saveTranscriptionLlmSettings() {
        const nextSettings = collectTranscriptionLlmSettingsFromForm();
        const selectedModel = String(nextSettings.model || "").trim();
        const systemPromptValue = String(nextSettings.system_prompt || "").trim();

        if (selectedModel === "") {
            setTranscriptionFeedback("Оберіть LLM-модель у налаштуваннях блоку “Хід роботи LLM”.", "is-error");
            transcriptionLlmModel?.focus();
            return false;
        }

        if (systemPromptValue === "") {
            setTranscriptionFeedback("Системний prompt для LLM-оцінювання не може бути порожнім.", "is-error");
            transcriptionLlmSystemPrompt?.focus();
            return false;
        }

        const saved = writeStoredTranscriptionLlmSettings({
            model: selectedModel,
            system_prompt: systemPromptValue,
            generation_settings: nextSettings.generation_settings,
            generation_settings_by_model: nextSettings.generation_settings_by_model,
        });

        if (!saved) {
            setTranscriptionFeedback("Браузер не дозволив зберегти профілі моделей для блоку LLM. Перевірте режим приватного перегляду або сховище браузера.", "is-error");
            return false;
        }

        syncTranscriptionLlmSettingsForm();
        setTranscriptionFeedback("Локальні налаштування блоку “Хід роботи LLM” збережено.", "is-success");

        return true;
    }

    function openTranscriptionAiSettingsModal({ focusPrompt = false } = {}) {
        if (!transcriptionAiSettingsModal) {
            return;
        }

        transcriptionAiSettingsDraft = normalizeTranscriptionAiSettings(transcriptionAiSettingsState);
        syncTranscriptionAiSettingsForm(transcriptionAiSettingsDraft);
        transcriptionAiSettingsModal.hidden = false;
        syncBodyScrollLock();

        requestAnimationFrame(() => {
            if (focusPrompt) {
                transcriptionAiPrompt?.focus();
                return;
            }

            transcriptionAiModel?.focus();
        });
    }

    function closeTranscriptionAiSettingsModal() {
        if (!transcriptionAiSettingsModal) {
            return;
        }

        transcriptionAiSettingsModal.hidden = true;
        transcriptionAiSettingsDraft = null;
        syncBodyScrollLock();
    }

    function saveTranscriptionAiSettings() {
        const nextSettings = collectTranscriptionAiSettingsFromForm();
        const promptValue = String(nextSettings.prompt || "").trim();

        if (promptValue === "") {
            setTranscriptionFeedback("Вкажіть промт для AI-обробки тексту в налаштуваннях поруч із кнопкою AI.", "is-error");
            transcriptionAiPrompt?.focus();
            return false;
        }

        const saved = writeStoredTranscriptionAiSettings({
            model: nextSettings.model,
            prompt: promptValue,
            generation_settings: nextSettings.generation_settings,
            generation_settings_by_model: nextSettings.generation_settings_by_model,
        });

        if (!saved) {
            setTranscriptionFeedback("Браузер не дозволив зберегти профілі моделей для AI-обробки. Перевірте режим приватного перегляду або сховище браузера.", "is-error");
            return false;
        }

        syncTranscriptionAiSettingsForm();
        setTranscriptionFeedback("Налаштування AI-обробки збережено. Тепер можна запускати кнопку AI над текстом.", "is-success");

        return true;
    }

    function consumeTranscriptionAiStreamEvent(eventPayload = {}) {
        const type = String(eventPayload?.type || "").trim();

        if (type === "status") {
            const phase = String(eventPayload?.phase || "").trim();
            const nextStatus = phase === "completed"
                ? "completed"
                : (phase === "failed" ? "failed" : "running");

            renderTranscriptionAiLiveState(
                nextStatus,
                String(eventPayload?.message || "").trim() || "AI-обробка виконується...",
                { stickToBottom: true }
            );
            return { done: false, errorMessage: "" };
        }

        if (type === "thinking") {
            transcriptionAiThinkingText = String(eventPayload?.text || "");
            renderTranscriptionAiLiveState(
                "running",
                "Модель міркує над текстом. Поточний reasoning видно нижче.",
                { stickToBottom: true }
            );
            return { done: false, errorMessage: "" };
        }

        if (type === "response") {
            transcriptionAiResponseText = String(eventPayload?.text || "");
            renderTranscriptionAiLiveState(
                "running",
                "Модель формує список виправлень. Потік оновлюється в реальному часі.",
                { stickToBottom: true }
            );
            return { done: false, errorMessage: "" };
        }

        if (type === "completed") {
            const finalText = String(eventPayload?.text || "");
            const corrections = Array.isArray(eventPayload?.corrections) ? eventPayload.corrections : [];
            if (finalText !== "") {
                transcriptionAiResponseText = buildTranscriptionAiCorrectionsPreview(corrections);
            }

            renderTranscriptionAiLiveState(
                "completed",
                String(eventPayload?.message || "").trim() || "AI-обробку завершено.",
                { stickToBottom: true }
            );

            return {
                done: true,
                errorMessage: "",
                payload: {
                    text: finalText,
                    model: String(eventPayload?.model || "").trim(),
                    message: String(eventPayload?.message || "").trim(),
                    corrections,
                    raw_corrections: String(eventPayload?.raw_corrections || ""),
                },
            };
        }

        if (type === "error") {
            const errorMessage = String(eventPayload?.message || "").trim() || "Не вдалося виконати AI-обробку тексту.";
            renderTranscriptionAiLiveState("failed", errorMessage, { stickToBottom: true });

            return {
                done: false,
                errorMessage,
            };
        }

        return { done: false, errorMessage: "" };
    }

    async function consumeTranscriptionAiRewriteStream(response) {
        const reader = response.body?.getReader?.();
        if (!reader) {
            throw new Error("Браузер не підтримує потокове читання AI-відповіді.");
        }

        const decoder = new TextDecoder();
        let buffer = "";
        let completedPayload = null;
        let streamErrorMessage = "";

        while (true) {
            const { value, done } = await reader.read();
            if (done) {
                break;
            }

            buffer += decoder.decode(value, { stream: true });

            while (true) {
                const newlineIndex = buffer.indexOf("\n");
                if (newlineIndex === -1) {
                    break;
                }

                const line = buffer.slice(0, newlineIndex).trim();
                buffer = buffer.slice(newlineIndex + 1);

                if (line === "") {
                    continue;
                }

                const parsed = JSON.parse(line);
                const result = consumeTranscriptionAiStreamEvent(parsed);

                if (result.errorMessage) {
                    streamErrorMessage = result.errorMessage;
                }

                if (result.done) {
                    completedPayload = result.payload || null;
                }
            }
        }

        const tail = buffer.trim();
        if (tail !== "") {
            const parsed = JSON.parse(tail);
            const result = consumeTranscriptionAiStreamEvent(parsed);

            if (result.errorMessage) {
                streamErrorMessage = result.errorMessage;
            }

            if (result.done) {
                completedPayload = result.payload || null;
            }
        }

        if (streamErrorMessage !== "") {
            throw new Error(streamErrorMessage);
        }

        if (!completedPayload || String(completedPayload.text || "").trim() === "") {
            throw new Error("Сервер завершив AI-обробку без виправленого тексту.");
        }

        return completedPayload;
    }

    function stopTranscriptionAiRewrite({ hidePanel = false } = {}) {
        if (!activeTranscriptionAiRewriteController) {
            if (hidePanel) {
                hideTranscriptionAiLiveBox();
                hideTranscriptionAiInputBox();
            }

            return;
        }

        isStoppingTranscriptionAiRewrite = true;
        activeTranscriptionAiRewriteController.abort();
        activeTranscriptionAiRewriteController = null;
        isTranscriptionAiRewriteRunning = false;

        if (transcriptionResultText) {
            transcriptionResultText.value = transcriptionAiSourceTextSnapshot;
        }

        transcriptionAiThinkingText = "";
        transcriptionAiResponseText = "Зупинено користувачем. Початковий текст відновлено.";
        renderTranscriptionAiLiveState("stopped", "AI-обробку зупинено. Початковий текст повернуто.", { stickToBottom: true });
        setTranscriptionBusy(false);
        setTranscriptionFeedback("AI-обробку зупинено. Початковий текст відновлено, можна запускати ще раз.", "");

        if (hidePanel) {
            hideTranscriptionAiLiveBox();
            hideTranscriptionAiInputBox();
        }
    }

    function closeTranscriptionAiLiveBox() {
        if (hasActiveTranscriptionAiRewrite()) {
            stopTranscriptionAiRewrite({ hidePanel: true });
            return;
        }

        hideTranscriptionAiLiveBox();
    }

    function closeTranscriptionAiInputBox() {
        if (hasActiveTranscriptionAiRewrite()) {
            stopTranscriptionAiRewrite({ hidePanel: true });
            return;
        }

        hideTranscriptionAiInputBox();
    }

    function setChecklistFeedback(message, tone = "") {
        if (!checklistFeedback) {
            return;
        }

        checklistFeedback.textContent = message;
        checklistFeedback.classList.remove("is-loading", "is-success", "is-error");

        if (tone) {
            checklistFeedback.classList.add(tone);
        }
    }

    function normalizeChecklistItem(item = {}) {
        const label = String(item?.label || item?.text || item?.name || "")
            .replace(/\s+/g, " ")
            .trim();
        const rawMaxPoints = Number(item?.max_points ?? item?.maxPoints ?? 10);
        const max_points = Number.isFinite(rawMaxPoints)
            ? Math.max(1, Math.min(100, Math.round(rawMaxPoints)))
            : 10;

        return {
            label,
            max_points
        };
    }

    function parseChecklistItemsText(value) {
        return String(value || "")
            .replace(/\r\n?/g, "\n")
            .split("\n")
            .map((line) => line.replace(/^\s*(?:[-*•]|\d+[.)])\s*/u, "").trim())
            .filter(Boolean)
            .map((label) => ({
                label,
                max_points: 10
            }));
    }

    function normalizeChecklistItems(items, { keepEmpty = false } = {}) {
        const sourceItems = Array.isArray(items) ? items : [];
        const normalized = sourceItems.map((item) => normalizeChecklistItem(item));

        return normalized.filter((item) => keepEmpty || item.label !== "");
    }

    function buildChecklistSummary(itemsSource) {
        const items = Array.isArray(itemsSource)
            ? normalizeChecklistItems(itemsSource)
            : parseChecklistItemsText(itemsSource);

        if (items.length === 0) {
            return "Поки без опису.";
        }

        return `${items.slice(0, 3).map((item) => item.label).join(", ")}.`;
    }

    function serializeChecklistItemsText(items) {
        return normalizeChecklistItems(items)
            .map((item, index) => `${index + 1}. ${item.label}`)
            .join("\n");
    }

    function escapeMarkdownTableCell(value) {
        return String(value || "")
            .replace(/\r\n?/g, "\n")
            .replace(/\|/g, "\\|")
            .split("\n")
            .map((line) => line.replace(/\s+/g, " ").trim())
            .filter(Boolean)
            .join("<br>");
    }

    function checklistExportBaseFileName(name) {
        const safeName = String(name || "checklist")
            .trim()
            .replace(/[\\/:*?"<>|]+/g, "-")
            .replace(/\s+/g, "-")
            .replace(/-+/g, "-")
            .replace(/^-|-$/g, "")
            .slice(0, 90);

        return `${safeName || "checklist"}-table`;
    }

    function checklistExportFileName(name, extension = "md") {
        return `${checklistExportBaseFileName(name)}.${extension}`;
    }

    function buildChecklistMarkdownTable(payload) {
        const items = normalizeChecklistItems(payload?.items || []);
        const totalPoints = items.reduce((sum, item) => sum + Number(item.max_points || 0), 0);
        const checklistNameText = String(payload?.name || "Чек-лист").trim() || "Чек-лист";
        const checklistTypeText = String(payload?.type || "Загальний сценарій").trim() || "Загальний сценарій";
        const rows = items.map((item, index) => (
            `| ${index + 1} | ${escapeMarkdownTableCell(item.label)} | ${item.max_points} |`
        ));

        return [
            `# ${checklistNameText}`,
            "",
            `Тип сценарію: ${checklistTypeText}`,
            `Кількість пунктів: ${items.length}`,
            `Максимум балів: ${totalPoints}`,
            "",
            "| № | Пункт чек-листа | Балів |",
            "|---:|---|---:|",
            ...rows,
            `|  | **Всього** | **${totalPoints}** |`,
            "",
        ].join("\n");
    }

    function escapeCsvCell(value) {
        return `"${String(value ?? "").replaceAll('"', '""')}"`;
    }

    function buildChecklistSpreadsheetRows(payload) {
        const items = normalizeChecklistItems(payload?.items || []);
        const totalPoints = items.reduce((sum, item) => sum + Number(item.max_points || 0), 0);
        const checklistNameText = String(payload?.name || "Чек-лист").trim() || "Чек-лист";
        const checklistTypeText = String(payload?.type || "Загальний сценарій").trim() || "Загальний сценарій";

        return [
            ["Назва", checklistNameText],
            ["Тип сценарію", checklistTypeText],
            ["Кількість пунктів", items.length],
            ["Максимум балів", totalPoints],
            [],
            ["№", "Пункт чек-листа", "Балів"],
            ...items.map((item, index) => [index + 1, item.label, item.max_points]),
            ["", "Всього", totalPoints],
        ];
    }

    function buildChecklistCsvTable(payload) {
        return `\ufeff${buildChecklistSpreadsheetRows(payload)
            .map((row) => row.map((cell) => escapeCsvCell(cell)).join(","))
            .join("\r\n")}`;
    }

    function buildChecklistExcelTable(payload) {
        const rows = buildChecklistSpreadsheetRows(payload);

        return `\ufeff<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        table { border-collapse: collapse; font-family: Arial, sans-serif; }
        td, th { border: 1px solid #cfd8e8; padding: 8px 10px; vertical-align: top; }
        th { background: #eef4ff; font-weight: 700; }
    </style>
</head>
<body>
    <table>
        ${rows.map((row, rowIndex) => {
            if (row.length === 0) {
                return "<tr><td colspan=\"3\"></td></tr>";
            }

            const tagName = rowIndex === 5 ? "th" : "td";

            return `<tr>${row.map((cell) => `<${tagName}>${escapeHtml(cell)}</${tagName}>`).join("")}</tr>`;
        }).join("")}
    </table>
</body>
</html>`;
    }

    function downloadTextFile(filename, content, mimeType = "text/markdown;charset=utf-8") {
        const blob = new Blob([content], { type: mimeType });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");

        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    }

    function exportChecklistTable(format = "chatgpt") {
        const payload = currentChecklistPayload();

        if (!Array.isArray(payload.items) || payload.items.length === 0) {
            setChecklistFeedback("Немає пунктів для експорту. Додайте хоча б один пункт чек-листа.", "is-error");
            return;
        }

        if (format === "excel") {
            downloadTextFile(
                checklistExportFileName(payload.name, "xls"),
                buildChecklistExcelTable(payload),
                "application/vnd.ms-excel;charset=utf-8",
            );
            setChecklistFeedback("Таблицю чек-листа скачано у форматі Excel.", "is-success");
            closeChecklistExportModal();
            return;
        }

        if (format === "google") {
            downloadTextFile(
                checklistExportFileName(payload.name, "csv"),
                buildChecklistCsvTable(payload),
                "text/csv;charset=utf-8",
            );
            setChecklistFeedback("Таблицю чек-листа скачано у CSV-форматі для Google Sheets.", "is-success");
            closeChecklistExportModal();
            return;
        }

        downloadTextFile(
            checklistExportFileName(payload.name, "md"),
            buildChecklistMarkdownTable(payload),
        );
        setChecklistFeedback("Таблицю чек-листа скачано у Markdown-форматі для ChatGPT.", "is-success");
        closeChecklistExportModal();
    }

    function getChecklistEditorItems({ keepEmpty = false } = {}) {
        if (!checklistItemsEditor) {
            return [];
        }

        return [...checklistItemsEditor.querySelectorAll("[data-checklist-item-row]")]
            .map((row) => ({
                label: row.querySelector("[data-checklist-item-label]")?.value || "",
                max_points: row.querySelector("[data-checklist-item-max-points]")?.value || 10,
            }))
            .map((item) => normalizeChecklistItem(item))
            .filter((item) => keepEmpty || item.label !== "");
    }

    function renderChecklistItemsEditor(items) {
        if (!checklistItemsEditor) {
            return;
        }

        const rows = normalizeChecklistItems(items, { keepEmpty: true });
        const safeRows = rows.length > 0 ? rows : [{ label: "", max_points: 10 }];

        checklistItemsEditor.innerHTML = safeRows.map((item, index) => `
            <div class="checklist-item-row" data-checklist-item-row data-index="${index}">
                <span
                    class="checklist-item-number"
                    data-checklist-item-drag-handle
                    draggable="true"
                    title="Перетягніть, щоб змінити порядок пунктів"
                    aria-label="Перетягніть, щоб змінити порядок пункту"
                >${index + 1}</span>
                <textarea
                    class="textarea-input checklist-item-label-input"
                    data-checklist-item-label
                    maxlength="2000"
                    placeholder="Назва пункту"
                    rows="1"
                >${escapeHtml(item.label)}</textarea>
                <input
                    class="text-input checklist-item-points-input"
                    type="number"
                    min="1"
                    max="100"
                    value="${escapeAttribute(item.max_points)}"
                    data-checklist-item-max-points
                    placeholder="10"
                >
                <button
                    type="button"
                    class="ghost-button checklist-item-add-button"
                    data-checklist-item-add
                    aria-label="Додати пункт після цього"
                    title="Додати пункт"
                >+</button>
            </div>
        `).join("");

        syncChecklistItemLabelHeights();
    }

    const checklistItemCollapsedHeight = 46;
    let draggedChecklistItemRow = null;
    let checklistDropTargetRow = null;
    let checklistDropInsertAfter = false;
    let didReorderChecklistItems = false;

    function setChecklistItemLabelHeight(textarea, expanded) {
        if (!(textarea instanceof HTMLTextAreaElement)) {
            return;
        }

        textarea.classList.toggle("is-expanded", expanded);

        if (!expanded) {
            textarea.style.height = `${checklistItemCollapsedHeight}px`;
            textarea.scrollTop = 0;
            return;
        }

        textarea.style.height = "auto";
        textarea.style.height = `${Math.max(checklistItemCollapsedHeight, textarea.scrollHeight)}px`;
    }

    function syncChecklistItemLabelHeights() {
        if (!checklistItemsEditor) {
            return;
        }

        checklistItemsEditor.querySelectorAll("[data-checklist-item-label]").forEach((field) => {
            setChecklistItemLabelHeight(field, field === document.activeElement);
        });
    }

    function renumberChecklistItemRows() {
        if (!checklistItemsEditor) {
            return;
        }

        [...checklistItemsEditor.querySelectorAll("[data-checklist-item-row]")].forEach((row, index) => {
            row.dataset.index = String(index);

            const number = row.querySelector("[data-checklist-item-drag-handle]");
            if (number) {
                number.textContent = String(index + 1);
            }
        });
    }

    function clearChecklistItemDropMarker() {
        checklistItemsEditor?.querySelectorAll(".is-drop-before, .is-drop-after").forEach((row) => {
            row.classList.remove("is-drop-before", "is-drop-after");
        });

        checklistDropTargetRow = null;
        checklistDropInsertAfter = false;
    }

    function setChecklistItemDropMarker(targetRow, insertAfter) {
        clearChecklistItemDropMarker();

        if (!targetRow || targetRow === draggedChecklistItemRow) {
            return;
        }

        checklistDropTargetRow = targetRow;
        checklistDropInsertAfter = Boolean(insertAfter);
        targetRow.classList.add(insertAfter ? "is-drop-after" : "is-drop-before");
    }

    function moveChecklistItemRow(sourceRow, targetRow, insertAfter) {
        if (!checklistItemsEditor || !sourceRow || !targetRow || sourceRow === targetRow) {
            return false;
        }

        const referenceNode = insertAfter ? targetRow.nextElementSibling : targetRow;
        if (referenceNode === sourceRow) {
            return false;
        }

        checklistItemsEditor.insertBefore(sourceRow, referenceNode);
        renumberChecklistItemRows();
        syncChecklistItemLabelHeights();

        return true;
    }

    function finishChecklistItemDrag() {
        if (draggedChecklistItemRow) {
            draggedChecklistItemRow.classList.remove("is-dragging");
        }

        clearChecklistItemDropMarker();
        draggedChecklistItemRow = null;

        if (didReorderChecklistItems) {
            didReorderChecklistItems = false;
            markChecklistEditorDirty();
        }
    }

    function findChecklist(checklistId) {
        return checklists.find((item) => item.id === checklistId) || null;
    }

    function currentChecklistPayload() {
        const items = getChecklistEditorItems();

        return {
            id: checklistIdField?.value?.trim() || "",
            name: checklistName?.value?.trim() || "",
            type: checklistType?.value || "Загальний сценарій",
            prompt: checklistPrompt?.value?.trim() || "",
            items,
            items_text: serializeChecklistItemsText(items)
        };
    }

    function checklistPayloadFromChecklist(checklist, overrides = {}) {
        const items = normalizeChecklistItems(
            checklist?.items && Array.isArray(checklist.items)
                ? checklist.items
                : parseChecklistItemsText(checklist?.items_text || "")
        );

        return {
            id: overrides.id ?? checklist?.id ?? "",
            name: String(overrides.name ?? checklist?.name ?? "").trim(),
            type: overrides.type ?? checklist?.type ?? "Загальний сценарій",
            prompt: String(overrides.prompt ?? checklist?.prompt ?? "").trim(),
            items,
            items_text: serializeChecklistItemsText(items)
        };
    }

    function loadChecklistIntoEditor(checklist, { markDirty = false } = {}) {
        if (!checklistName || !checklistType || !checklistItemsEditor || !checklistIdField) {
            return;
        }

        checklistIdField.value = checklist?.id || "";
        checklistName.value = checklist?.name || "";
        checklistType.value = checklist?.type || "Загальний сценарій";
        if (checklistPrompt) {
            checklistPrompt.value = checklist?.prompt || "";
        }
        renderChecklistItemsEditor(
            checklist?.items && Array.isArray(checklist.items)
                ? checklist.items
                : parseChecklistItemsText(checklist?.items_text || "")
        );
        activeChecklistId = checklist?.id || null;
        isChecklistEditorDirty = Boolean(markDirty);
    }

    function renderChecklistList() {
        if (!checklistList) {
            return;
        }

        checklistList.innerHTML = checklists.map((checklist) => `
            <div
                class="stack-item ${checklist.id === activeChecklistId ? "is-active" : ""} ${checklist.id === activeChecklistRenameId ? "is-renaming" : ""}"
                data-checklist-id="${escapeAttribute(checklist.id)}"
                role="button"
                tabindex="0"
            >
                <div class="stack-item-title-row">
                    ${checklist.id === activeChecklistRenameId
                        ? `
                            <input
                                class="text-input checklist-rename-input"
                                type="text"
                                value="${escapeAttribute(checklist.name)}"
                                data-checklist-rename-input
                                data-checklist-rename-id="${escapeAttribute(checklist.id)}"
                                aria-label="Нова назва чек-листа"
                            >
                        `
                        : `<strong>${escapeHtml(checklist.name)}</strong>`
                    }
                </div>
                ${checklist.id === activeChecklistRenameId
                    ? ""
                    : `
                        <div class="stack-item-actions">
                            <button
                                type="button"
                                class="icon-button stack-item-rename-button"
                                data-checklist-rename-trigger="${escapeAttribute(checklist.id)}"
                                aria-label="Перейменувати чек-лист ${escapeAttribute(checklist.name)}"
                                title="Перейменувати"
                            >
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12 20h9"></path>
                                    <path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4Z"></path>
                                </svg>
                            </button>
                                <button
                                    type="button"
                                    class="icon-button stack-item-delete-button"
                                    data-checklist-delete-trigger="${escapeAttribute(checklist.id)}"
                                    aria-label="Видалити чек-лист ${escapeAttribute(checklist.name)}"
                                    title="Видалити"
                                >
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M3 6h18"></path>
                                    <path d="M8 6V4h8v2"></path>
                                    <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                                    <path d="M10 11v6"></path>
                                    <path d="M14 11v6"></path>
                                </svg>
                            </button>
                        </div>
                    `
                }
            </div>
        `).join("");
    }

    function focusChecklistRenameInput() {
        if (!checklistList) {
            return;
        }

        requestAnimationFrame(() => {
            const renameInput = checklistList.querySelector("[data-checklist-rename-input]");
            renameInput?.focus();
            renameInput?.select();
        });
    }

    function findChecklistCardElement(checklistId) {
        if (!checklistList || !checklistId) {
            return null;
        }

        return [...checklistList.querySelectorAll("[data-checklist-id]")]
            .find((element) => element.dataset.checklistId === checklistId) || null;
    }

    function revealChecklistCard(checklistId) {
        const checklistCard = findChecklistCardElement(checklistId);
        if (!checklistCard) {
            return;
        }

        requestAnimationFrame(() => {
            checklistCard.scrollIntoView({
                behavior: "smooth",
                block: "nearest",
            });
        });
    }

    function beginChecklistRename(checklistId) {
        if (!checklistId) {
            return;
        }

        activeChecklistRenameId = checklistId;
        renderChecklistList();
        focusChecklistRenameInput();
    }

    function runPendingChecklistAction() {
        const nextRenameId = pendingChecklistRenameId;
        const nextSelectionId = pendingChecklistSelectionId;
        const nextDeleteId = pendingChecklistDeleteId;

        pendingChecklistRenameId = null;
        pendingChecklistSelectionId = null;
        pendingChecklistDeleteId = null;

        if (nextRenameId) {
            beginChecklistRename(nextRenameId);
            return;
        }

        if (nextDeleteId) {
            openChecklistDeleteModal(nextDeleteId);
            return;
        }

        if (nextSelectionId) {
            selectChecklist(nextSelectionId, { syncDropdown: true });
            setChecklistFeedback("Редагуємо збережений чек-лист. Його пункти й бали можна відразу використати для оцінювання.", "");
        }
    }

    function cancelChecklistRename() {
        if (!activeChecklistRenameId) {
            return;
        }

        activeChecklistRenameId = null;
        pendingChecklistRenameId = null;
        pendingChecklistSelectionId = null;
        pendingChecklistDeleteId = null;
        renderChecklistList();
    }

    function syncTranscriptionChecklistOptions(preferredValue = null) {
        if (!transcriptionChecklist) {
            return;
        }

        const nextValue = preferredValue || transcriptionChecklist.value || activeChecklistId || checklists[0]?.id || "";

        transcriptionChecklist.innerHTML = checklists.map((checklist) => `
            <option value="${escapeAttribute(checklist.id)}">${escapeHtml(checklist.name)}</option>
        `).join("");

        if (checklists.some((checklist) => checklist.id === nextValue)) {
            transcriptionChecklist.value = nextValue;
            return;
        }

        transcriptionChecklist.value = checklists[0]?.id || "";
    }

    function selectChecklist(checklistId, { syncDropdown = true } = {}) {
        const checklist = findChecklist(checklistId);
        if (!checklist) {
            return;
        }

        loadChecklistIntoEditor(checklist, { markDirty: false });
        writeStoredChecklistSelectionId(checklist.id);
        renderChecklistList();

        if (syncDropdown && transcriptionChecklist) {
            transcriptionChecklist.value = checklist.id;
        }
    }

    function resetChecklistEditor() {
        loadChecklistIntoEditor({
            id: "",
            name: "Новий чек-лист",
            type: "Загальний сценарій",
            prompt: "",
            items: [
                { label: "Представився та окреслив мету дзвінка", max_points: 10 },
                { label: "З'ясував потребу клієнта", max_points: 10 },
                { label: "Поставив уточнювальні запитання", max_points: 10 },
                { label: "Зафіксував наступний крок", max_points: 10 },
            ]
        }, { markDirty: true });
        renderChecklistList();
        setChecklistFeedback("Створіть новий чек-лист і збережіть його, щоб він з'явився у списку оцінювання.", "");
    }

    function buildChecklistCopyName(name) {
        const fallbackName = "Новий чек-лист";
        const sourceName = String(name || "").trim() || fallbackName;
        const match = sourceName.match(/^(.*?)(?:\s+\(копія(?:\s+(\d+))?\))?$/u);
        const rootName = (match?.[1] || sourceName).trim() || fallbackName;
        const copyNamePattern = new RegExp(`^${rootName.replace(/[.*+?^${}()|[\\]\\\\]/g, "\\$&")}\\s+\\(копія(?:\\s+(\\d+))?\\)$`, "u");

        const highestCopyNumber = checklists.reduce((highest, checklist) => {
            const checklistName = String(checklist?.name || "").trim();
            const copyMatch = checklistName.match(copyNamePattern);
            if (!copyMatch) {
                return highest;
            }

            const copyNumber = Number(copyMatch[1] || 1);
            return Number.isFinite(copyNumber) && copyNumber > highest ? copyNumber : highest;
        }, 0);

        const nextCopyNumber = highestCopyNumber + 1;

        return nextCopyNumber === 1
            ? `${rootName} (копія)`
            : `${rootName} (копія ${nextCopyNumber})`;
    }

    async function saveChecklist() {
        const payload = currentChecklistPayload();

        if (!payload.name) {
            setChecklistFeedback("Вкажіть назву чек-листа.", "is-error");
            return;
        }

        if (!Array.isArray(payload.items) || payload.items.length === 0) {
            setChecklistFeedback("Додайте пункти чек-листа.", "is-error");
            return;
        }

        if (!checklistSaveButton) {
            return;
        }

        checklistSaveButton.disabled = true;
        setChecklistFeedback("Зберігаємо чек-лист і оновлюємо промпт для оцінювання...", "is-loading");

        try {
            const result = await requestChecklistSave(payload);

            checklists = Array.isArray(result.checklists) ? result.checklists : checklists;
            activeChecklistId = result.checklist?.id || activeChecklistId;
            selectChecklist(activeChecklistId, { syncDropdown: true });
            syncTranscriptionChecklistOptions(activeChecklistId);
            lastBootstrapSyncAt = Date.now();
            setChecklistFeedback("Чек-лист збережено. Саме цей текст тепер піде в промпт Qwen під час оцінювання.", "is-success");
        } catch (error) {
            const message = error instanceof TypeError || String(error?.message || "").includes("Failed to fetch")
                ? "Сервер не відповів на збереження чек-листа. Оновіть сторінку й спробуйте ще раз."
                : (error.message || "Не вдалося зберегти чек-лист.");

            setChecklistFeedback(message, "is-error");
        } finally {
            checklistSaveButton.disabled = false;
        }
    }

    async function requestChecklistSave(payload) {
        const response = await fetch(checklistsEndpoint, {
            method: "POST",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json"
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json().catch(() => ({}));
        if (!response.ok) {
            const validationMessage = result.errors
                ? Object.values(result.errors).flat().find(Boolean)
                : null;
            throw new Error(validationMessage || result.message || "Не вдалося зберегти чек-лист.");
        }

        return result;
    }

    async function requestChecklistDuplicate(checklistId) {
        const response = await fetch(`${checklistsEndpoint}/${encodeURIComponent(checklistId)}/duplicate`, {
            method: "POST",
            headers: {
                Accept: "application/json"
            }
        });

        const result = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(result.message || "Не вдалося створити копію чек-листа.");
        }

        return result;
    }

    async function requestChecklistDelete(checklistId) {
        const response = await fetch(`${checklistsEndpoint}/${encodeURIComponent(checklistId)}`, {
            method: "DELETE",
            headers: {
                Accept: "application/json"
            }
        });

        const result = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(result.message || "Не вдалося видалити чек-лист.");
        }

        return result;
    }

    function resolveChecklistSelectionAfterDelete(nextChecklists, deletedChecklistId, deletedIndex, fallbackId = null) {
        if (
            activeChecklistId
            && activeChecklistId !== deletedChecklistId
            && nextChecklists.some((checklist) => checklist.id === activeChecklistId)
        ) {
            return activeChecklistId;
        }

        return nextChecklists[deletedIndex]?.id
            || nextChecklists[Math.max(0, deletedIndex - 1)]?.id
            || fallbackId
            || nextChecklists[0]?.id
            || null;
    }

    async function confirmChecklistDelete() {
        if (!pendingChecklistDeleteId || isChecklistDeleteSubmitting) {
            return;
        }

        const deleteChecklistId = pendingChecklistDeleteId;
        const deleteChecklist = findChecklist(deleteChecklistId);
        if (!deleteChecklist) {
            closeChecklistDeleteModal();
            return;
        }

        const deleteIndex = checklists.findIndex((checklist) => checklist.id === deleteChecklistId);
        isChecklistDeleteSubmitting = true;
        checklistDeleteConfirmButton.disabled = true;
        checklistDeleteCancelButton.disabled = true;
        setChecklistFeedback(`Видаляємо чек-лист «${deleteChecklist.name}»...`, "is-loading");

        try {
            const result = await requestChecklistDelete(deleteChecklistId);

            checklists = Array.isArray(result.checklists) ? result.checklists : checklists;
            defaultChecklistId = result.defaultChecklistId || checklists[0]?.id || null;
            activeChecklistId = resolveChecklistSelectionAfterDelete(
                checklists,
                deleteChecklistId,
                deleteIndex >= 0 ? deleteIndex : 0,
                defaultChecklistId
            );

            if (activeChecklistId) {
                selectChecklist(activeChecklistId, { syncDropdown: true });
                syncTranscriptionChecklistOptions(activeChecklistId);
            } else {
                writeStoredChecklistSelectionId("");
                renderChecklistList();
                syncTranscriptionChecklistOptions("");
            }

            lastBootstrapSyncAt = Date.now();
            closeChecklistDeleteModal();
            setChecklistFeedback(`Чек-лист «${deleteChecklist.name}» видалено.`, "is-success");
        } catch (error) {
            const message = error instanceof TypeError || String(error?.message || "").includes("Failed to fetch")
                ? "Сервер не відповів при видаленні чек-листа."
                : (error.message || "Не вдалося видалити чек-лист.");

            checklistDeleteConfirmButton.disabled = false;
            checklistDeleteCancelButton.disabled = false;
            isChecklistDeleteSubmitting = false;
            setChecklistFeedback(message, "is-error");
        }
    }

    async function commitChecklistRename() {
        if (!activeChecklistRenameId || isChecklistRenameSaving) {
            return;
        }

        const renameId = activeChecklistRenameId;
        const checklist = findChecklist(renameId);
        const renameInput = checklistList?.querySelector("[data-checklist-rename-input]");
        const nextName = renameInput?.value?.trim() || "";

        if (!checklist) {
            cancelChecklistRename();
            return;
        }

        if (!nextName) {
            setChecklistFeedback("Вкажіть нову назву чек-листа.", "is-error");
            focusChecklistRenameInput();
            return;
        }

        if (nextName === String(checklist.name || "").trim()) {
            activeChecklistRenameId = null;
            renderChecklistList();
            runPendingChecklistAction();
            return;
        }

        isChecklistRenameSaving = true;
        setChecklistFeedback("Зберігаємо нову назву чек-листа...", "is-loading");

        const payload = checklistIdField?.value?.trim() === renameId
            ? {
                ...currentChecklistPayload(),
                id: renameId,
                name: nextName,
            }
            : checklistPayloadFromChecklist(checklist, { name: nextName });

        try {
            const result = await requestChecklistSave(payload);

            checklists = Array.isArray(result.checklists) ? result.checklists : checklists;
            activeChecklistRenameId = null;

            if (checklistIdField?.value?.trim() === renameId && checklistName) {
                checklistName.value = nextName;
                isChecklistEditorDirty = false;
            }

            renderChecklistList();
            syncTranscriptionChecklistOptions(transcriptionChecklist?.value || activeChecklistId || renameId);
            lastBootstrapSyncAt = Date.now();
            setChecklistFeedback("Назву чек-листа збережено.", "is-success");
            runPendingChecklistAction();
        } catch (error) {
            const message = error instanceof TypeError || String(error?.message || "").includes("Failed to fetch")
                ? "Сервер не відповів при перейменуванні чек-листа."
                : (error.message || "Не вдалося перейменувати чек-лист.");

            setChecklistFeedback(message, "is-error");
            focusChecklistRenameInput();
        } finally {
            isChecklistRenameSaving = false;
        }
    }

    async function duplicateChecklist() {
        const currentId = checklistIdField?.value?.trim() || "";
        const draft = currentChecklistPayload();

        if (!checklistDuplicateButton) {
            return;
        }

        if (!Array.isArray(draft.items) || draft.items.length === 0) {
            setChecklistFeedback("Додайте пункти чек-листа перед клонуванням.", "is-error");
            return;
        }

        checklistDuplicateButton.disabled = true;
        setChecklistFeedback("Клонуємо чек-лист із поточним промптом і пунктами...", "is-loading");

        try {
            let result;

            if (currentId) {
                const duplicateResult = await requestChecklistDuplicate(currentId);
                const clonedId = duplicateResult.checklist?.id || "";

                if (!clonedId) {
                    throw new Error("Сервер не повернув ідентифікатор копії чек-листа.");
                }

                result = await requestChecklistSave({
                    ...draft,
                    id: clonedId,
                    name: buildChecklistCopyName(draft.name),
                });
            } else {
                result = await requestChecklistSave({
                    ...draft,
                    id: "",
                    name: buildChecklistCopyName(draft.name),
                });
            }

            checklists = Array.isArray(result.checklists) ? result.checklists : checklists;
            activeChecklistId = result.checklist?.id || activeChecklistId;
            selectChecklist(activeChecklistId, { syncDropdown: true });
            syncTranscriptionChecklistOptions(activeChecklistId);
            revealChecklistCard(activeChecklistId);
            lastBootstrapSyncAt = Date.now();
            setChecklistFeedback("Копію створено й відкрито. Вона вже містить поточний промпт, тип сценарію та всі пункти.", "is-success");
        } catch (error) {
            const message = error instanceof TypeError || String(error?.message || "").includes("Failed to fetch")
                ? "Сервер не відповів при клонуванні чек-листа."
                : (error.message || "Не вдалося клонувати чек-лист.");

            setChecklistFeedback(message, "is-error");
        } finally {
            checklistDuplicateButton.disabled = false;
        }
    }

    function setTranscriptionFeedback(message, tone = "") {
        if (!transcriptionFeedback) {
            return;
        }

        transcriptionFeedback.textContent = message;
        transcriptionFeedback.classList.remove("is-loading", "is-success", "is-error");

        if (tone) {
            transcriptionFeedback.classList.add(tone);
        }
    }

    function setTranscriptionBusy(isBusy, mode = "transcription") {
        const hasAnyRuntimeTask = Boolean(
            isBusy
            || activeTranscriptionTaskId
            || activeTranscriptionRequestController
            || activeEvaluationJobId
            || activeEvaluationRequestController
            || activeEvaluationPollController
            || isTranscriptionAiRewriteRunning
            || activeTranscriptionAiRewriteController
        );

        if (transcriptionRunButton) {
            transcriptionRunButton.disabled = hasAnyRuntimeTask;
            transcriptionRunButton.classList.toggle("is-running", isBusy && mode === "transcription");
            const runButtonLabel = isBusy && mode === "transcription"
                ? "Виконуємо..."
                : defaultTranscriptionButtonLabel;
            transcriptionRunButton.setAttribute("aria-label", runButtonLabel);
            transcriptionRunButton.setAttribute("title", runButtonLabel);
        }

        syncTranscriptionAiRewriteControls();
        syncTranscriptionLlmControls();
        syncTranscriptionStopButtonState();
    }

    function isAbortError(error) {
        return Boolean(error && typeof error === "object" && error.name === "AbortError");
    }

    function hasActiveEvaluationJob() {
        return Boolean(activeEvaluationJobId || activeEvaluationRequestController || activeEvaluationPollController);
    }

    function syncTranscriptionStopButtonState() {
        if (!transcriptionStopButton) {
            syncTranscriptionAiRewriteControls();
            return;
        }

        const hasActiveTask = Boolean(
            activeEvaluationJobId
            || activeTranscriptionTaskId
            || activeTranscriptionRequestController
            || activeEvaluationRequestController
            || activeEvaluationPollController
        );

        transcriptionStopButton.hidden = !hasActiveTask;
        transcriptionStopButton.disabled = !hasActiveTask || isStoppingTranscriptionTasks;
        transcriptionStopButton.setAttribute("aria-label", defaultStopButtonLabel);
        transcriptionStopButton.setAttribute("title", defaultStopButtonLabel);
        syncTranscriptionAiRewriteControls();
        syncTranscriptionLlmControls();
    }

    function clearActiveTranscriptionTasks() {
        activeEvaluationJobId = null;
        activeTranscriptionTaskId = null;
        activeTranscriptionRequestController = null;
        activeEvaluationRequestController = null;
        activeEvaluationPollController = null;
        syncTranscriptionStopButtonState();
    }

    async function stopTranscriptionTasks() {
        if (isStoppingTranscriptionTasks) {
            return;
        }

        isStoppingTranscriptionTasks = true;

        if (transcriptionStopButton) {
            transcriptionStopButton.disabled = true;
        }

        const jobIdToCancel = activeEvaluationJobId;
        const taskIdToCancel = activeTranscriptionTaskId;

        activeTranscriptionRequestController?.abort();
        activeEvaluationRequestController?.abort();
        activeEvaluationPollController?.abort();

        activeTranscriptionRequestController = null;
        activeEvaluationRequestController = null;
        activeEvaluationPollController = null;

        let feedbackMessage = "Поточне завдання зупинено. Можна запускати новий запуск.";

        if (jobIdToCancel) {
            try {
                const response = await fetch(`${transcriptionEvaluationEndpoint}/${encodeURIComponent(jobIdToCancel)}`, {
                    method: "DELETE",
                    headers: {
                        Accept: "application/json"
                    }
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(payload.message || "Не вдалося зупинити фонове оцінювання.");
                }

                feedbackMessage = payload.message || feedbackMessage;
            } catch (error) {
                if (!isAbortError(error)) {
                    feedbackMessage = error instanceof TypeError || String(error?.message || "").includes("Failed to fetch")
                        ? "Завдання зупинено локально, але сервер не підтвердив очищення. Можна запускати знову."
                        : (error.message || "Завдання зупинено локально. Можна запускати знову.");
                }
            }
        }

        if (taskIdToCancel) {
            try {
                const response = await fetch(`${transcriptionTaskEndpoint}/${encodeURIComponent(taskIdToCancel)}`, {
                    method: "DELETE",
                    headers: {
                        Accept: "application/json"
                    }
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(payload.message || "Не вдалося зупинити транскрибацію.");
                }

                if (!jobIdToCancel) {
                    feedbackMessage = payload.message || feedbackMessage;
                }
            } catch (error) {
                if (!isAbortError(error) && !jobIdToCancel) {
                    feedbackMessage = error instanceof TypeError || String(error?.message || "").includes("Failed to fetch")
                        ? "Завдання зупинено локально, але сервер не підтвердив очищення. Можна запускати знову."
                        : (error.message || "Завдання зупинено локально. Можна запускати знову.");
                }
            }
        }

        clearActiveTranscriptionTasks();
        renderTranscriptionEvaluation(null);
        resetTranscriptionLlmMonitor("Завдання зупинено та очищено. Можна запускати нову транскрибацію або оцінювання.");
        setTranscriptionBusy(false);
        setTranscriptionFeedback(feedbackMessage, "");
        isStoppingTranscriptionTasks = false;
    }

    async function runTranscriptionAiRewrite() {
        const transcriptText = transcriptionResultText?.value?.trim() || "";

        if (transcriptText === "") {
            setTranscriptionFeedback("У полі результату поки немає тексту для AI-обробки.", "is-error");
            return;
        }

        if (isTranscriptionAiRewriteRunning) {
            return;
        }

        if (activeTranscriptionTaskId || activeTranscriptionRequestController) {
            setTranscriptionFeedback("Дочекайтеся завершення поточної транскрибації, а потім запускайте AI-обробку тексту.", "is-error");
            return;
        }

        const runtimeSettings = transcriptionAiSettingsModal && !transcriptionAiSettingsModal.hidden
            ? collectTranscriptionAiSettingsFromForm()
            : transcriptionAiSettingsState;
        const selectedModel = String(runtimeSettings?.model || settingsModel?.value || transcriptionSettings?.llm_model || "").trim();
        const promptText = String(runtimeSettings?.prompt || "").trim();
        const generationSettings = normalizeTranscriptionAiGenerationSettings(runtimeSettings?.generation_settings);
        const generationSettingsByModel = {
            ...(runtimeSettings?.generation_settings_by_model || {}),
            [selectedModel]: generationSettings,
        };

        if (selectedModel === "") {
            setTranscriptionFeedback("Спочатку оберіть AI-модель у налаштуваннях поруч із кнопкою AI.", "is-error");
            openTranscriptionAiSettingsModal();
            return;
        }

        if (promptText === "") {
            setTranscriptionFeedback("Спочатку вкажіть промт у налаштуваннях AI-обробки.", "is-error");
            openTranscriptionAiSettingsModal({ focusPrompt: true });
            return;
        }

        const aiBackendPayload = {
            text: transcriptText,
            prompt: promptText,
            model: selectedModel,
            generation_settings: generationSettings,
            stream: true,
        };
        const aiRequestPreview = buildTranscriptionAiRequestPreview({
            model: selectedModel,
            prompt: promptText,
            text: transcriptText,
            generationSettings,
        });

        const savedAiSettings = writeStoredTranscriptionAiSettings({
            model: selectedModel,
            prompt: promptText,
            generation_settings: generationSettings,
            generation_settings_by_model: generationSettingsByModel,
        });

        if (!savedAiSettings) {
            setTranscriptionFeedback("Браузер не дозволив зберегти локальні налаштування AI-моделі. Перевірте доступ до localStorage.", "is-error");
            return;
        }

        closeTranscriptionAiSettingsModal();
        isTranscriptionAiRewriteRunning = true;
        isStoppingTranscriptionAiRewrite = false;
        transcriptionAiSourceTextSnapshot = transcriptionResultText?.value || "";
        transcriptionAiThinkingText = "";
        transcriptionAiResponseText = "";
        activeTranscriptionAiRewriteController = new AbortController();
        showTranscriptionAiLiveBox();
        renderTranscriptionAiInputPreview(aiRequestPreview);
        renderTranscriptionAiLiveState(
            "running",
            `Підключаємося до ${selectedModel} і запускаємо живу AI-обробку тексту...`,
            { stickToBottom: true }
        );
        setTranscriptionBusy(true, "ai-rewrite");
        setTranscriptionFeedback(`AI-модель ${selectedModel} шукає орфографічні виправлення. Заміну виконає скрипт тільки по точних збігах.`, "is-loading");

        try {
            const response = await fetch(transcriptionAiRewriteEndpoint, {
                method: "POST",
                headers: {
                    Accept: "application/x-ndjson, application/json",
                    "Content-Type": "application/json"
                },
                signal: activeTranscriptionAiRewriteController.signal,
                body: JSON.stringify(aiBackendPayload)
            });

            if (!response.ok) {
                const payload = await response.json().catch(() => ({}));
                const validationMessage = payload.errors
                    ? Object.values(payload.errors).flat().find(Boolean)
                    : null;

                throw new Error(validationMessage || payload.message || "Не вдалося виконати AI-обробку тексту.");
            }

            const payload = await consumeTranscriptionAiRewriteStream(response);
            const rewrittenText = String(payload.text || "").trim();
            if (rewrittenText === "") {
                throw new Error("Скрипт отримав порожній результат. Старий текст не було змінено.");
            }

            const textChangedByAi = rewrittenText !== transcriptionAiSourceTextSnapshot;
            const appliedReplacementCount = countTranscriptionAiAppliedCorrections(payload.corrections);
            transcriptionResultText.value = rewrittenText;
            if (textChangedByAi && !hasActiveEvaluationJob()) {
                renderTranscriptionEvaluation(null, "Текст було змінено через AI-обробку. Щоб отримати актуальну оцінку, запустіть її повторно.");
                resetTranscriptionLlmMonitor("Після AI-обробки попередній монітор LLM очищено. За потреби запустіть нове оцінювання по чек-листу.");
            }
            setTranscriptionFeedback(
                payload.message || (hasActiveEvaluationJob()
                    ? `AI-обробку завершено. Скрипт застосував ${appliedReplacementCount} автозамін, а поточне LLM-оцінювання продовжує працювати по тексту, з яким було запущене. Модель: ${selectedModel}.`
                    : `AI-обробку завершено. Скрипт застосував ${appliedReplacementCount} точних автозамін. Модель: ${selectedModel}.`),
                "is-success"
            );
        } catch (error) {
            if (isAbortError(error)) {
                return;
            }

            renderTranscriptionAiLiveState(
                "failed",
                error instanceof TypeError || String(error?.message || "").includes("Failed to fetch")
                    ? "Сервер не відповів на потоковий AI-запит. Перевірте Ollama та спробуйте ще раз."
                    : (error.message || "Не вдалося виконати AI-обробку тексту."),
                { stickToBottom: true }
            );

            const message = error instanceof TypeError || String(error?.message || "").includes("Failed to fetch")
                ? "Сервер не відповів на запит AI-обробки. Перевірте Ollama та спробуйте ще раз."
                : (error.message || "Не вдалося виконати AI-обробку тексту.");

            setTranscriptionFeedback(message, "is-error");
        } finally {
            activeTranscriptionAiRewriteController = null;
            isTranscriptionAiRewriteRunning = false;
            isStoppingTranscriptionAiRewrite = false;
            setTranscriptionBusy(false);
        }
    }

    function llmJobStatusLabel(status) {
        switch (String(status || "").trim()) {
            case "running":
                return "В роботі";
            case "completed":
                return "Готово";
            case "failed":
                return "Помилка";
            case "pending":
            default:
                return "Очікування";
        }
    }

    function llmJobPhaseLabel(phase, status) {
        const normalizedPhase = String(phase || "").trim();
        const normalizedStatus = String(status || "").trim();
        const statelessQuestionMatch = normalizedPhase.match(/^stateless_question_(\d+)_of_(\d+)$/);
        const statelessRetryMatch = normalizedPhase.match(/^stateless_retry_(\d+)_of_(\d+)$/);

        if (statelessQuestionMatch) {
            return `Stateless-оцінювання: модель отримала повний транскрипт і оцінює пункт ${statelessQuestionMatch[1]} із ${statelessQuestionMatch[2]}.`;
        }

        if (statelessRetryMatch) {
            return `Повторюємо незалежний запит для пункту ${statelessRetryMatch[1]} із ${statelessRetryMatch[2]} з повним транскриптом.`;
        }

        if (normalizedPhase === "stateless_completed") {
            return "Усі пункти чек-листа оцінено незалежними запитами. Формуємо фінальні бали.";
        }

        if (normalizedPhase === "prompt_prepared") {
            return "Backend вже зібрав точний prompt для Qwen. Його можна відкрити нижче ще до завершення відповіді.";
        }

        if (normalizedPhase === "streaming") {
            return "Ollama вже відповідає потоково. Thinking і текст відповіді оновлюються нижче.";
        }

        if (normalizedPhase === "completed" || normalizedStatus === "completed") {
            return "Оцінювання завершено. Нижче збережено лог, thinking та фінальну відповідь LLM.";
        }

        if (normalizedPhase === "failed" || normalizedStatus === "failed") {
            return "Фонове оцінювання завершилося помилкою. Лог нижче допоможе зрозуміти, на якому етапі це сталося.";
        }

        if (normalizedStatus === "running") {
            return "Фоновий процес працює. Лог і сирий потік від LLM оновлюються автоматично.";
        }

        return "Після запуску тут буде видно етапи роботи Qwen / Ollama.";
    }

    function formatJobLogTimestamp(value) {
        if (!value) {
            return "--:--:--";
        }

        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return "--:--:--";
        }

        return date.toLocaleTimeString("uk-UA", {
            hour: "2-digit",
            minute: "2-digit",
            second: "2-digit"
        });
    }

    function buildLlmConsoleText(job) {
        const logs = Array.isArray(job?.logs) ? job.logs : [];
        const thinking = String(job?.llm?.thinking || "").trim();
        const response = String(job?.llm?.response || "").trim();
        const lines = [];

        if (logs.length > 0) {
            lines.push("=== ЛОГ СЕРВІСУ ===");
            logs.forEach((entry) => {
                const timestamp = formatJobLogTimestamp(entry?.created_at || null);
                const channel = String(entry?.channel || "status").trim().toUpperCase();
                const message = String(entry?.message || "").trim();

                if (message !== "") {
                    lines.push(`[${timestamp}] [${channel}] ${message}`);
                }
            });
        }

        if (thinking !== "") {
            if (lines.length > 0) {
                lines.push("");
            }

            lines.push("=== THINKING / МІРКУВАННЯ ===");
            lines.push(thinking);
        }

        if (response !== "") {
            if (lines.length > 0) {
                lines.push("");
            }

            lines.push("=== СИРА ВІДПОВІДЬ LLM ===");
            lines.push(response);
        }

        if (lines.length === 0) {
            return "Поки що LLM ще не запускалась.";
        }

        return lines.join("\n");
    }

    function transcriptionLlmPromptToggleLabel(isExpanded = false, hasPrompt = false) {
        if (!hasPrompt) {
            return "Очікуємо prompt для Qwen";
        }

        return isExpanded
            ? "Сховати prompt для Qwen"
            : "Показати prompt для Qwen";
    }

    function toggleTranscriptionLlmPrompt(forceExpanded = null) {
        if (!transcriptionLlmPromptToggle || !transcriptionLlmPromptDetails || transcriptionLlmPromptToggle.disabled) {
            return;
        }

        const expanded = forceExpanded === null
            ? transcriptionLlmPromptDetails.hidden
            : Boolean(forceExpanded);

        transcriptionLlmPromptDetails.hidden = !expanded;
        transcriptionLlmPromptToggle.setAttribute("aria-expanded", expanded ? "true" : "false");
        transcriptionLlmPromptToggle.textContent = transcriptionLlmPromptToggleLabel(
            expanded,
            Boolean(transcriptionLlmPromptToggle.dataset.hasPrompt === "true")
        );
    }

    function buildLlmPromptText(job) {
        const systemPrompt = String(job?.llm?.system_prompt || "").trim();
        const prompt = String(job?.llm?.prompt || "").trim();
        const sections = [];

        if (systemPrompt !== "") {
            sections.push("=== SYSTEM PROMPT ===");
            sections.push(systemPrompt);
        }

        if (prompt !== "") {
            if (sections.length > 0) {
                sections.push("");
            }

            sections.push("=== USER PROMPT ===");
            sections.push(prompt);
        }

        return sections.length > 0
            ? sections.join("\n")
            : "Після запуску тут з'явиться точний runtime-prompt, який backend відправив у Qwen / Ollama.";
    }

    function renderTranscriptionLlmPrompt(job = null) {
        const systemPrompt = String(job?.llm?.system_prompt || "").trim();
        const prompt = String(job?.llm?.prompt || "").trim();
        const hasPrompt = systemPrompt !== "" || prompt !== "";
        const wasExpanded = Boolean(
            transcriptionLlmPromptToggle
            && transcriptionLlmPromptToggle.dataset.hasPrompt === "true"
            && transcriptionLlmPromptToggle.getAttribute("aria-expanded") === "true"
        );

        if (transcriptionLlmPromptText) {
            transcriptionLlmPromptText.textContent = buildLlmPromptText(job);
            transcriptionLlmPromptText.scrollTop = 0;
        }

        if (!transcriptionLlmPromptToggle || !transcriptionLlmPromptDetails) {
            return;
        }

        transcriptionLlmPromptToggle.disabled = !hasPrompt;
        transcriptionLlmPromptToggle.dataset.hasPrompt = hasPrompt ? "true" : "false";
        transcriptionLlmPromptToggle.textContent = transcriptionLlmPromptToggleLabel(wasExpanded && hasPrompt, hasPrompt);
        transcriptionLlmPromptToggle.setAttribute("aria-expanded", wasExpanded && hasPrompt ? "true" : "false");
        transcriptionLlmPromptDetails.hidden = !(wasExpanded && hasPrompt);
    }

    function resetTranscriptionLlmMonitor(message = "Поки що LLM ще не запускалась.") {
        if (transcriptionLlmStatus) {
            transcriptionLlmStatus.textContent = llmJobStatusLabel("pending");
            transcriptionLlmStatus.classList.remove("is-pending", "is-running", "is-completed", "is-failed");
            transcriptionLlmStatus.classList.add("is-pending");
        }

        if (transcriptionLlmPhase) {
            transcriptionLlmPhase.textContent = "Після запуску тут буде видно етапи роботи Qwen / Ollama.";
        }

        if (transcriptionLlmConsole) {
            transcriptionLlmConsole.textContent = message;
            transcriptionLlmConsole.scrollTop = 0;
        }

        renderTranscriptionLlmPrompt(null);
    }

    function renderTranscriptionLlmPending(checklistName = "") {
        if (transcriptionLlmStatus) {
            transcriptionLlmStatus.textContent = llmJobStatusLabel("running");
            transcriptionLlmStatus.classList.remove("is-pending", "is-running", "is-completed", "is-failed");
            transcriptionLlmStatus.classList.add("is-running");
        }

        if (transcriptionLlmPhase) {
            transcriptionLlmPhase.textContent = checklistName !== ""
                ? `Готуємо фонове оцінювання для чек-листа «${checklistName}».`
                : "Готуємо фонове оцінювання чек-листа.";
        }

        if (transcriptionLlmConsole) {
            transcriptionLlmConsole.textContent = "Очікуємо старт фонового процесу оцінювання...\n\nЩойно Ollama почне відповідати, тут з'являться етапи, thinking та сирий текст відповіді.";
            transcriptionLlmConsole.scrollTop = 0;
        }

        renderTranscriptionLlmPrompt(null);
    }

    function renderTranscriptionLlmMonitor(job = null) {
        if (!job) {
            resetTranscriptionLlmMonitor();
            return;
        }

        const status = String(job.status || "pending");
        const phase = String(job?.llm?.phase || status || "pending");
        const nextConsoleText = buildLlmConsoleText(job);

        if (transcriptionLlmStatus) {
            transcriptionLlmStatus.textContent = llmJobStatusLabel(status);
            transcriptionLlmStatus.classList.remove("is-pending", "is-running", "is-completed", "is-failed");
            transcriptionLlmStatus.classList.add(
                status === "completed"
                    ? "is-completed"
                    : (status === "failed"
                        ? "is-failed"
                        : (status === "running" ? "is-running" : "is-pending"))
            );
        }

        if (transcriptionLlmPhase) {
            transcriptionLlmPhase.textContent = llmJobPhaseLabel(phase, status);
        }

        if (transcriptionLlmConsole) {
            const shouldStickToBottom = Math.abs(
                (transcriptionLlmConsole.scrollHeight - transcriptionLlmConsole.clientHeight) - transcriptionLlmConsole.scrollTop
            ) < 24;

            transcriptionLlmConsole.textContent = nextConsoleText;

            if (status === "running" || shouldStickToBottom) {
                transcriptionLlmConsole.scrollTop = transcriptionLlmConsole.scrollHeight;
            }
        }

        renderTranscriptionLlmPrompt(job);
    }

    function setSettingsFeedback(message, tone = "") {
        if (!settingsFeedback) {
            return;
        }

        settingsFeedback.textContent = message;
        settingsFeedback.classList.remove("is-loading", "is-success", "is-error");

        if (tone) {
            settingsFeedback.classList.add(tone);
        }
    }

    function formatSliderNumber(value, digits = 0) {
        const numeric = Number(value);
        if (!Number.isFinite(numeric)) {
            return "—";
        }

        return numeric.toLocaleString("uk-UA", {
            minimumFractionDigits: digits,
            maximumFractionDigits: digits,
        });
    }

    function syncLlmSettingsSliderValues() {
        if (settingsLlmTemperature && settingsLlmTemperatureValue) {
            settingsLlmTemperatureValue.textContent = formatSliderNumber(settingsLlmTemperature.value, 1);
        }

        if (settingsLlmNumCtx && settingsLlmNumCtxValue) {
            settingsLlmNumCtxValue.textContent = formatSliderNumber(settingsLlmNumCtx.value, 0);
        }
    }

    function syncLlmModelOptions(preferredModel = null) {
        if (!settingsModel) {
            return;
        }

        const availableModels = Array.isArray(transcriptionSettings?.llm_available_models)
            ? transcriptionSettings.llm_available_models
            : [];
        const currentValue = preferredModel || settingsModel.value || transcriptionSettings?.llm_model || "";
        const nextModels = [...new Set([
            ...availableModels.filter((value) => typeof value === "string" && value.trim() !== ""),
            currentValue
        ].filter(Boolean))];

        settingsModel.innerHTML = nextModels.map((model) => `
            <option value="${escapeAttribute(model)}">${escapeHtml(model)}</option>
        `).join("");

        if (nextModels.includes(currentValue)) {
            settingsModel.value = currentValue;
            return;
        }

        settingsModel.value = nextModels[0] || "";
    }

    function syncSettingsFormFromState() {
        syncSelectOptions(
            settingsProvider,
            transcriptionSettings?.llm_available_providers || [transcriptionSettings?.llm_provider || "ollama"],
            transcriptionSettings?.llm_provider || "ollama"
        );
        syncSelectOptions(
            settingsWhisperModel,
            transcriptionSettings?.available_models || [transcriptionSettings?.transcription_model || "large-v3"],
            transcriptionSettings?.transcription_model || "large-v3"
        );

        if (settingsApiUrl) {
            settingsApiUrl.value = transcriptionSettings?.llm_api_url || "";
        }

        if (settingsWhisperInitialPrompt) {
            settingsWhisperInitialPrompt.value = transcriptionSettings?.transcription_initial_prompt || "";
        }

        if (settingsLlmThinkingEnabled) {
            settingsLlmThinkingEnabled.checked = false;
            settingsLlmThinkingEnabled.disabled = true;
        }

        if (settingsLlmTemperature) {
            settingsLlmTemperature.value = String(transcriptionSettings?.llm_temperature ?? 0.2);
        }

        if (settingsLlmNumCtx) {
            settingsLlmNumCtx.value = String(transcriptionSettings?.llm_num_ctx ?? 4096);
        }

        if (settingsLlmTopK) {
            settingsLlmTopK.value = String(transcriptionSettings?.llm_top_k ?? 40);
        }

        if (settingsLlmTopP) {
            settingsLlmTopP.value = String(transcriptionSettings?.llm_top_p ?? 0.9);
        }

        if (settingsLlmRepeatPenalty) {
            settingsLlmRepeatPenalty.value = String(transcriptionSettings?.llm_repeat_penalty ?? 1.1);
        }

        if (settingsLlmNumPredict) {
            settingsLlmNumPredict.value = String(transcriptionSettings?.llm_num_predict ?? 256);
        }

        if (settingsLlmSeed) {
            settingsLlmSeed.value = transcriptionSettings?.llm_seed ?? "";
        }

        if (settingsLlmTimeoutSeconds) {
            settingsLlmTimeoutSeconds.value = String(transcriptionSettings?.llm_timeout_seconds ?? 600);
        }

        if (settingsSpeakerDiarizationEnabled) {
            settingsSpeakerDiarizationEnabled.checked = Boolean(transcriptionSettings?.speaker_diarization_enabled);
        }

        if (settingsApiKey) {
            settingsApiKey.value = "";
            settingsApiKey.placeholder = Boolean(transcriptionSettings?.llm_has_api_key)
                ? "Ключ уже збережено. Введіть новий тільки якщо хочете замінити."
                : "Необовʼязково для локального Ollama";
        }

        if (settingsSpeakerDiarizationToken) {
            settingsSpeakerDiarizationToken.value = "";
            settingsSpeakerDiarizationToken.placeholder = Boolean(transcriptionSettings?.speaker_diarization_has_token)
                ? "Токен уже збережено. Введіть новий тільки якщо хочете замінити."
                : "hf_xxx";
        }

        syncLlmModelOptions(transcriptionSettings?.llm_model || settingsModel?.value || "");
        const nextTranscriptionAiSettings = normalizeTranscriptionAiSettings(
            transcriptionAiSettingsModal && !transcriptionAiSettingsModal.hidden && transcriptionAiSettingsDraft
                ? transcriptionAiSettingsDraft
                : transcriptionAiSettingsState
        );
        const nextTranscriptionLlmSettings = normalizeTranscriptionLlmSettings(
            transcriptionLlmSettingsModal && !transcriptionLlmSettingsModal.hidden && transcriptionLlmSettingsDraft
                ? transcriptionLlmSettingsDraft
                : transcriptionLlmSettingsState
        );

        if (transcriptionAiSettingsModal && !transcriptionAiSettingsModal.hidden) {
            transcriptionAiSettingsDraft = nextTranscriptionAiSettings;
            syncTranscriptionAiSettingsForm(transcriptionAiSettingsDraft);
        } else {
            transcriptionAiSettingsState = nextTranscriptionAiSettings;
            syncTranscriptionAiSettingsForm();
        }

        if (transcriptionLlmSettingsModal && !transcriptionLlmSettingsModal.hidden) {
            transcriptionLlmSettingsDraft = nextTranscriptionLlmSettings;
            syncTranscriptionLlmSettingsForm(transcriptionLlmSettingsDraft);
        } else {
            transcriptionLlmSettingsState = nextTranscriptionLlmSettings;
            syncTranscriptionLlmSettingsForm();
        }
        syncLlmSettingsSliderValues();
    }

    function applyBootstrapPayload(payload) {
        const nextCalls = Array.isArray(payload?.calls) ? payload.calls : [];
        const nextChecklists = Array.isArray(payload?.checklists) ? payload.checklists : [];
        const nextDefaultChecklistId = typeof payload?.defaultChecklistId === "string" && payload.defaultChecklistId !== ""
            ? payload.defaultChecklistId
            : (nextChecklists[0]?.id || null);
        const nextTranscriptionSettings = payload?.transcriptionSettings && typeof payload.transcriptionSettings === "object"
            ? payload.transcriptionSettings
            : {};
        const nextActiveEvaluationJob = payload?.activeEvaluationJob && typeof payload.activeEvaluationJob === "object"
            ? payload.activeEvaluationJob
            : null;

        calls = isInteractionHistoryMode
            ? filterInteractionHistoryRequestCalls(interactionHistoryRequest, nextCalls)
            : nextCalls;
        checklists = nextChecklists;
        defaultChecklistId = nextDefaultChecklistId;
        rebuildInteractionCountIndex();

        const nextUploadLimit = Number(payload?.transcriptionUploadLimitBytes);
        if (Number.isFinite(nextUploadLimit) && nextUploadLimit > 0) {
            transcriptionServerUploadLimitBytes = nextUploadLimit;
        }

        replaceObjectContents(transcriptionSettings, nextTranscriptionSettings);
        syncCallsState({ preserveCustomRange: true });
        renderFilterOptions();
        renderDateRangeSummary();
        renderRows();
        renderManagersRows();
        reopenActiveCallModal();

        if (hasChecklistRefreshLock()) {
            if (!activeChecklistRenameId) {
                renderChecklistList();
            }

            syncTranscriptionChecklistOptions(transcriptionChecklist?.value || activeChecklistId || defaultChecklistId || "");
        } else {
            activeChecklistId = resolveExistingChecklistId(
                activeChecklistId,
                storedChecklistSelectionId,
                defaultChecklistId,
                checklists[0]?.id || null
            );

            if (activeChecklistId) {
                selectChecklist(activeChecklistId, { syncDropdown: true });
            } else {
                writeStoredChecklistSelectionId("");
                renderChecklistList();
                syncTranscriptionChecklistOptions("");
            }
        }

        if (!isSettingsDirty) {
            syncSettingsFormFromState();
        }

        syncTranscriptionChecklistState();
        if (isEvaluationJobActive(nextActiveEvaluationJob)) {
            resumeActiveEvaluationJob(nextActiveEvaluationJob);
        }
        lastBootstrapSyncAt = Date.now();
    }

    async function refreshPageBootstrap({ force = false } = {}) {
        if (!pageBootstrapEndpoint || isBootstrapRefreshing) {
            return;
        }

        if (!force && (Date.now() - lastBootstrapSyncAt) < pageBootstrapStaleAfterMs) {
            return;
        }

        isBootstrapRefreshing = true;

        try {
            const response = await fetch(pageBootstrapEndpoint, {
                headers: {
                    Accept: "application/json"
                },
                cache: "no-store"
            });

            if (!response.ok) {
                throw new Error(`Bootstrap refresh failed with status ${response.status}`);
            }

            const payload = await response.json().catch(() => ({}));
            applyBootstrapPayload(payload);
        } catch (error) {
            // Ignore transient refresh failures and keep the current UI state.
        } finally {
            isBootstrapRefreshing = false;
        }
    }

    async function saveTranscriptionSettings() {
        if (!settingsWhisperModel || !settingsSaveButton || !settingsApiUrl) {
            return;
        }

        const selectedModel = settingsWhisperModel.value;
        const selectedWhisperInitialPrompt = settingsWhisperInitialPrompt?.value ?? "";
        const selectedProvider = settingsProvider?.value?.trim() || transcriptionSettings?.llm_provider || "ollama";
        const selectedLlmModel = settingsModel?.value?.trim() || transcriptionSettings?.llm_model || "";
        const selectedApiUrl = settingsApiUrl.value?.trim() || "";
        const selectedApiKey = settingsApiKey?.value?.trim() || "";
        const selectedLlmThinkingEnabled = false;
        const selectedLlmTemperature = settingsLlmTemperature?.value ?? transcriptionSettings?.llm_temperature ?? 0.2;
        const selectedLlmNumCtx = settingsLlmNumCtx?.value ?? transcriptionSettings?.llm_num_ctx ?? 4096;
        const selectedLlmTopK = settingsLlmTopK?.value ?? transcriptionSettings?.llm_top_k ?? 40;
        const selectedLlmTopP = settingsLlmTopP?.value ?? transcriptionSettings?.llm_top_p ?? 0.9;
        const selectedLlmRepeatPenalty = settingsLlmRepeatPenalty?.value ?? transcriptionSettings?.llm_repeat_penalty ?? 1.1;
        const selectedLlmNumPredict = settingsLlmNumPredict?.value ?? transcriptionSettings?.llm_num_predict ?? 256;
        const selectedLlmSeed = settingsLlmSeed?.value?.trim() ?? "";
        const selectedLlmTimeoutSeconds = settingsLlmTimeoutSeconds?.value ?? transcriptionSettings?.llm_timeout_seconds ?? 600;
        const diarizationEnabled = speakerDiarizationEnabled();
        const diarizationToken = settingsSpeakerDiarizationToken?.value?.trim() || "";

        if (selectedApiUrl === "") {
            setSettingsFeedback("Вкажіть URL сервісу LLM / Ollama.", "is-error");
            return;
        }

        if (diarizationEnabled && !diarizationToken && !hasStoredSpeakerDiarizationToken()) {
            setSettingsFeedback("Щоб увімкнути коректне визначення автора реплік, додайте Hugging Face token для pyannote.", "is-error");
            return;
        }

        settingsSaveButton.disabled = true;
        setSettingsFeedback("Зберігаємо доступ до Ollama/API, faster-whisper і speaker diarization...", "is-loading");

        try {
            const response = await fetch(transcriptionSettingsEndpoint, {
                method: "PUT",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    transcription_model: selectedModel,
                    transcription_initial_prompt: selectedWhisperInitialPrompt,
                    llm_provider: selectedProvider,
                    llm_api_url: selectedApiUrl,
                    llm_api_key: selectedApiKey,
                    llm_model: selectedLlmModel,
                    llm_temperature: Number(selectedLlmTemperature),
                    llm_num_ctx: Number(selectedLlmNumCtx),
                    llm_top_k: Number(selectedLlmTopK),
                    llm_top_p: Number(selectedLlmTopP),
                    llm_repeat_penalty: Number(selectedLlmRepeatPenalty),
                    llm_seed: selectedLlmSeed === "" ? null : Number(selectedLlmSeed),
                    llm_num_predict: Number(selectedLlmNumPredict),
                    llm_timeout_seconds: Number(selectedLlmTimeoutSeconds),
                    llm_thinking_enabled: selectedLlmThinkingEnabled,
                    speaker_diarization_enabled: diarizationEnabled,
                    speaker_diarization_token: diarizationToken
                })
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const validationMessage = payload.errors
                    ? Object.values(payload.errors).flat().find(Boolean)
                    : null;
                throw new Error(validationMessage || payload.message || "Не вдалося зберегти налаштування транскрибації.");
            }

            transcriptionSettings.transcription_model = payload.settings?.transcription_model || selectedModel;
            transcriptionSettings.transcription_initial_prompt = payload.settings?.transcription_initial_prompt ?? selectedWhisperInitialPrompt;
            transcriptionSettings.speaker_diarization_enabled = Boolean(payload.settings?.speaker_diarization_enabled);
            transcriptionSettings.speaker_diarization_has_token = Boolean(payload.settings?.speaker_diarization_has_token);
            transcriptionSettings.llm_provider = payload.settings?.llm_provider || selectedProvider;
            transcriptionSettings.llm_api_url = payload.settings?.llm_api_url || selectedApiUrl;
            transcriptionSettings.llm_has_api_key = Boolean(payload.settings?.llm_has_api_key);
            transcriptionSettings.llm_model = payload.settings?.llm_model || selectedLlmModel;
            transcriptionSettings.llm_available_models = Array.isArray(payload.settings?.llm_available_models)
                ? payload.settings.llm_available_models
                : (transcriptionSettings.llm_available_models || [transcriptionSettings.llm_model]);
            transcriptionSettings.llm_temperature = payload.settings?.llm_temperature ?? Number(selectedLlmTemperature);
            transcriptionSettings.llm_num_ctx = payload.settings?.llm_num_ctx ?? Number(selectedLlmNumCtx);
            transcriptionSettings.llm_top_k = payload.settings?.llm_top_k ?? Number(selectedLlmTopK);
            transcriptionSettings.llm_top_p = payload.settings?.llm_top_p ?? Number(selectedLlmTopP);
            transcriptionSettings.llm_repeat_penalty = payload.settings?.llm_repeat_penalty ?? Number(selectedLlmRepeatPenalty);
            transcriptionSettings.llm_seed = payload.settings?.llm_seed ?? (selectedLlmSeed === "" ? null : Number(selectedLlmSeed));
            transcriptionSettings.llm_num_predict = payload.settings?.llm_num_predict ?? Number(selectedLlmNumPredict);
            transcriptionSettings.llm_timeout_seconds = payload.settings?.llm_timeout_seconds ?? Number(selectedLlmTimeoutSeconds);
            transcriptionSettings.llm_thinking_enabled = false;
            isSettingsDirty = false;
            syncSettingsFormFromState();
            lastBootstrapSyncAt = Date.now();

            const warning = transcriptionSettings.transcription_model === "large-v3"
                ? " На CPU ця модель може працювати довго і впиратися в 504 timeout."
                : "";
            const diarizationStatus = transcriptionSettings.speaker_diarization_enabled
                ? (transcriptionSettings.speaker_diarization_has_token
                    ? "Автор реплік увімкнено через pyannote."
                    : "Автор реплік увімкнено, але токен не збережено.")
                : "Автор реплік вимкнено.";
            setSettingsFeedback(
                `Ollama/API: ${transcriptionSettings.llm_api_url}. faster-whisper: ${transcriptionSettings.transcription_model}. ${diarizationStatus}${warning}`,
                "is-success"
            );
        } catch (error) {
            const message = error instanceof TypeError || String(error?.message || "").includes("Failed to fetch")
                ? "Сервер не відповів на запит збереження налаштувань. Спробуйте ще раз після оновлення сторінки."
                : (error.message || "Не вдалося зберегти налаштування транскрибації.");

            setSettingsFeedback(message, "is-error");
        } finally {
            settingsSaveButton.disabled = false;
        }
    }

    function transcriptionScoreToggleLabel(isExpanded = false) {
        const itemCount = Number(transcriptionScoreDetailsToggle?.dataset.itemCount || 0);
        const suffix = itemCount > 0 ? ` (${itemCount})` : "";
        return `${isExpanded ? "Сховати" : "Показати"} оцінки по пунктах${suffix}`;
    }

    function resetTranscriptionEvaluationDetails() {
        if (transcriptionScoreItems) {
            transcriptionScoreItems.innerHTML = "";
        }

        if (transcriptionScoreDetails) {
            transcriptionScoreDetails.hidden = true;
        }

        if (transcriptionScoreDetailsToggle) {
            transcriptionScoreDetailsToggle.hidden = true;
            transcriptionScoreDetailsToggle.dataset.itemCount = "0";
            transcriptionScoreDetailsToggle.setAttribute("aria-expanded", "false");
            transcriptionScoreDetailsToggle.textContent = transcriptionScoreToggleLabel(false);
        }
    }

    function toggleTranscriptionEvaluationDetails(forceExpanded = null) {
        if (!transcriptionScoreDetailsToggle || !transcriptionScoreDetails || transcriptionScoreDetailsToggle.hidden) {
            return;
        }

        const expanded = forceExpanded === null
            ? transcriptionScoreDetails.hidden
            : Boolean(forceExpanded);

        transcriptionScoreDetails.hidden = !expanded;
        transcriptionScoreDetailsToggle.setAttribute("aria-expanded", expanded ? "true" : "false");
        transcriptionScoreDetailsToggle.textContent = transcriptionScoreToggleLabel(expanded);
    }

    function renderTranscriptionEvaluationItems(items) {
        if (!transcriptionScoreDetailsToggle || !transcriptionScoreDetails || !transcriptionScoreItems) {
            return;
        }

        const normalizedItems = (Array.isArray(items) ? items : [])
            .map((item, index) => {
                const label = String(item?.label || item?.title || "").trim() || `Пункт ${index + 1}`;
                const comment = String(item?.comment || item?.text || "").trim();
                const numericScore = Number(item?.score);
                const numericMaxPoints = Number(item?.max_points ?? item?.maxPoints);
                const maxPoints = Number.isFinite(numericMaxPoints)
                    ? Math.max(1, Math.min(100, Math.round(numericMaxPoints)))
                    : null;
                const score = Number.isFinite(numericScore)
                    ? Math.max(0, Math.min(maxPoints ?? 100, Math.round(numericScore)))
                    : null;
                const numericPercentage = Number(item?.percentage);
                const percentage = Number.isFinite(numericPercentage)
                    ? Math.max(0, Math.min(100, Math.round(numericPercentage)))
                    : (score !== null && maxPoints ? Math.round((score / maxPoints) * 100) : null);

                return {
                    label,
                    comment: comment || "Коментар для цього пункту не повернено.",
                    score,
                    maxPoints,
                    percentage
                };
            })
            .filter((item) => item.label !== "" || item.comment !== "" || item.score !== null || item.maxPoints !== null);

        if (normalizedItems.length === 0) {
            resetTranscriptionEvaluationDetails();
            return;
        }

        transcriptionScoreItems.innerHTML = normalizedItems.map((item) => `
            <article class="transcription-score-item">
                <div class="transcription-score-item-main">
                    <h4 class="transcription-score-item-title">${escapeHtml(item.label)}</h4>
                    <p class="transcription-score-item-comment">${escapeHtml(item.comment)}</p>
                </div>
                <div class="score-chip ${item.percentage === null ? "is-muted" : scoreClass(item.percentage)}">${item.score === null ? "—" : escapeHtml(item.maxPoints ? `${item.score}/${item.maxPoints}` : item.score)}</div>
            </article>
        `).join("");

        transcriptionScoreDetailsToggle.hidden = false;
        transcriptionScoreDetailsToggle.dataset.itemCount = String(normalizedItems.length);
        toggleTranscriptionEvaluationDetails(false);
    }

    function renderTranscriptionEvaluation(evaluation, errorMessage = "") {
        transcriptionScoreValue.classList.remove("score-high", "score-mid", "score-low");

        if (!evaluation) {
            resetTranscriptionEvaluationDetails();
            transcriptionScoreValue.textContent = "--";
            transcriptionScoreChecklistName.textContent = errorMessage !== ""
                ? "Оцінювання не виконано"
                : (transcriptionEvaluate?.checked
                    ? "Оцінка з'явиться після запуску"
                    : "Оцінювання вимкнено");
            transcriptionScoreStrongSide.textContent = "—";
            transcriptionScoreFocus.textContent = errorMessage || "—";
            return;
        }

        const overallScore = Number(evaluation.score);
        const overallPercent = Number(evaluation.scorePercent);
        transcriptionScoreValue.textContent = Number.isFinite(overallScore)
            ? String(Math.round(overallScore))
            : "--";
        if (Number.isFinite(overallPercent)) {
            transcriptionScoreValue.classList.add(scoreClass(overallPercent));
        }
        transcriptionScoreChecklistName.textContent = evaluation.checklistName || "Чек-лист не вказано";
        transcriptionScoreStrongSide.textContent = evaluation.strongSide || "Сильну сторону не визначено.";
        transcriptionScoreFocus.textContent = evaluation.focus || "Фокус для покращення не визначено.";
        renderTranscriptionEvaluationItems(evaluation.items || []);
    }

    function renderTranscriptionEvaluationPending(checklistName = "") {
        resetTranscriptionEvaluationDetails();
        transcriptionScoreValue.classList.remove("score-high", "score-mid", "score-low");
        transcriptionScoreValue.textContent = "...";
        transcriptionScoreChecklistName.textContent = checklistName || "Оцінюємо дзвінок";
        transcriptionScoreStrongSide.textContent = "Qwen аналізує розмову по пунктах чек-листа.";
        transcriptionScoreFocus.textContent = "Після завершення тут з'являться підсумок і деталі по кожному пункту.";
        renderTranscriptionLlmPending(checklistName);
    }

    function syncTranscriptionChecklistState() {
        if (!transcriptionChecklist || !transcriptionChecklistRow || !transcriptionEvaluate) {
            return;
        }

        const enabled = transcriptionEvaluate.checked;
        transcriptionChecklist.disabled = !enabled;
        transcriptionChecklistRow.classList.toggle("is-disabled", !enabled);
        if (!enabled) {
            renderTranscriptionEvaluation(null);
            resetTranscriptionLlmMonitor("Оцінювання за чек-листом вимкнено.");
        }
    }

    function syncTranscriptionFileName() {
        if (!transcriptionFileInput || !transcriptionFileName) {
            return;
        }

        transcriptionFileName.textContent = transcriptionFileInput.files?.[0]?.name || "Файл не вибрано";
    }

    function sleep(ms) {
        return new Promise((resolve) => window.setTimeout(resolve, ms));
    }

    async function createTranscriptionTask() {
        const response = await fetch(transcriptionTaskEndpoint, {
            method: "POST",
            headers: {
                Accept: "application/json"
            }
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(payload.message || "Не вдалося підготувати службове завдання для транскрибації.");
        }

        const taskId = String(payload.task?.id || "").trim();
        if (taskId === "") {
            throw new Error("Сервер не повернув ідентифікатор транскрибації.");
        }

        return taskId;
    }

    async function pollTranscriptionEvaluationJob(jobId) {
        const controller = new AbortController();
        activeEvaluationPollController = controller;
        activeEvaluationJobId = jobId;
        syncTranscriptionStopButtonState();
        const pollIntervalMs = 2500;
        const maxTransientMissingPolls = 3;
        let transientMissingPolls = 0;

        try {
            while (true) {
                await sleep(pollIntervalMs);

                const response = await fetch(`${transcriptionEvaluationEndpoint}/${encodeURIComponent(jobId)}`, {
                    method: "GET",
                    headers: {
                        Accept: "application/json"
                    },
                    signal: controller.signal
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    if (response.status === 404) {
                        transientMissingPolls += 1;

                        if (transientMissingPolls <= maxTransientMissingPolls) {
                            continue;
                        }
                    }

                    throw new Error(payload.message || "Не вдалося отримати статус оцінювання.");
                }

                transientMissingPolls = 0;
                const job = payload.job || {};
                renderTranscriptionLlmMonitor(job);
                const status = String(job.status || "pending");

                if (status === "completed") {
                    activeEvaluationJobId = null;
                    syncTranscriptionStopButtonState();
                    return payload;
                }

                if (status === "failed") {
                    activeEvaluationJobId = null;
                    syncTranscriptionStopButtonState();
                    throw new Error(job.message || "Фонове оцінювання завершилося помилкою.");
                }
            }
        } finally {
            if (activeEvaluationPollController === controller) {
                activeEvaluationPollController = null;
                syncTranscriptionStopButtonState();
            }
        }
    }

    async function runTranscriptionEvaluation(transcription, checklistDraft) {
        const runtimeLlmSettings = transcriptionLlmSettingsModal && !transcriptionLlmSettingsModal.hidden
            ? collectTranscriptionLlmSettingsFromForm()
            : transcriptionLlmSettingsState;
        const selectedLlmModel = String(runtimeLlmSettings?.model || transcriptionSettings?.llm_model || "").trim();
        const systemPromptText = String(runtimeLlmSettings?.system_prompt || "").trim();
        const generationSettings = normalizeTranscriptionLlmGenerationSettings(runtimeLlmSettings?.generation_settings);
        const generationSettingsByModel = {
            ...(runtimeLlmSettings?.generation_settings_by_model || {}),
            [selectedLlmModel]: generationSettings,
        };

        if (selectedLlmModel === "") {
            throw new Error("Оберіть LLM-модель у налаштуваннях блоку “Хід роботи LLM”.");
        }

        if (systemPromptText === "") {
            throw new Error("Системний prompt для LLM-оцінювання не може бути порожнім.");
        }

        const savedLlmSettings = writeStoredTranscriptionLlmSettings({
            model: selectedLlmModel,
            system_prompt: systemPromptText,
            generation_settings: generationSettings,
            generation_settings_by_model: generationSettingsByModel,
        });

        if (!savedLlmSettings) {
            throw new Error("Браузер не дозволив зберегти локальні налаштування LLM-моделі. Перевірте доступ до localStorage.");
        }

        syncTranscriptionLlmSettingsForm();

        const controller = new AbortController();
        activeEvaluationRequestController = controller;
        syncTranscriptionStopButtonState();
        const activeCall = findCall(selectedCallId);
        const generalCallId = typeof activeCall?.generalCallId === "string"
            ? activeCall.generalCallId.trim()
            : "";

        try {
            const response = await fetch(transcriptionEvaluationEndpoint, {
                method: "POST",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json"
                },
                signal: controller.signal,
                body: JSON.stringify({
                    transcription: {
                        text: transcription?.text || "",
                        dialogueText: transcription?.dialogueText || "",
                    },
                    general_call_id: generalCallId,
                    checklist_id: transcriptionChecklist?.value || checklistDraft.id || "",
                    checklist_name: checklistDraft.name || "",
                    checklist_type: checklistDraft.type || "",
                    checklist_prompt: checklistDraft.prompt || "",
                    checklist_items: Array.isArray(checklistDraft.items) ? checklistDraft.items : [],
                    llm_settings: {
                        run_id: activeTranscriptionComparisonRunId || undefined,
                        model: selectedLlmModel,
                        system_prompt: systemPromptText,
                        ...generationSettings,
                    },
                })
            });

            const payload = await response.json().catch(() => ({}));
            activeTranscriptionComparisonRunId = String(payload?.run_id || "").trim();

            if (!response.ok) {
                throw new Error(payload.message || "Не вдалося запустити оцінювання дзвінка.");
            }

            const jobId = payload.job?.id || "";
            if (jobId === "") {
                throw new Error("Сервер не повернув ідентифікатор фонового оцінювання.");
            }

            activeEvaluationJobId = jobId;
            syncTranscriptionStopButtonState();
            syncTranscriptionChecklistState();
            renderTranscriptionLlmMonitor(payload.job || null);

            if (payload.reused_existing_job) {
                setTranscriptionFeedback(
                    payload.message || "Оцінювання вже виконується. Показуємо статус поточного фонового завдання.",
                    "is-loading"
                );
            }

            return pollTranscriptionEvaluationJob(jobId);
        } finally {
            if (activeEvaluationRequestController === controller) {
                activeEvaluationRequestController = null;
                syncTranscriptionStopButtonState();
                syncTranscriptionChecklistState();
            }
        }
    }

    function isEvaluationJobActive(job) {
        const status = String(job?.status || "").toLowerCase();
        const jobId = String(job?.id || "").trim();

        return jobId !== "" && (status === "pending" || status === "running");
    }

    function resumeActiveEvaluationJob(job, feedbackMessage = "") {
        if (!isEvaluationJobActive(job)) {
            return;
        }

        const jobId = String(job.id || "").trim();
        if (jobId === "") {
            return;
        }

        activeEvaluationJobId = jobId;
        renderTranscriptionLlmMonitor(job);
        renderTranscriptionEvaluation(job?.evaluation || null, "");
        syncTranscriptionStopButtonState();
        syncTranscriptionChecklistState();

        if (feedbackMessage) {
            setTranscriptionFeedback(feedbackMessage, "is-loading");
        } else if (transcriptionFeedback?.classList.contains("is-error")) {
            setTranscriptionFeedback("Фонове оцінювання ще виконується. Продовжуємо моніторинг поточного завдання.", "is-loading");
        }

        if (activeEvaluationPollController) {
            return;
        }

        pollTranscriptionEvaluationJob(jobId)
            .then((payload) => {
                renderTranscriptionLlmMonitor(payload.job || null);
                renderTranscriptionEvaluation(payload.job?.evaluation || null, "");
                setTranscriptionFeedback("Фонове оцінювання завершено. Результат уже синхронізовано на сторінці.", "is-success");
            })
            .catch((evaluationError) => {
                if (isAbortError(evaluationError) || isStoppingTranscriptionTasks) {
                    return;
                }

                const evaluationMessage = evaluationError instanceof TypeError || String(evaluationError?.message || "").includes("Failed to fetch")
                    ? "Не вдалося відновити статус фонового оцінювання. Воно могло ще виконуватися у фоні."
                    : (evaluationError.message || "Не вдалося відновити активне фонове оцінювання.");

                renderTranscriptionEvaluation(null, evaluationMessage);
                resetTranscriptionLlmMonitor(evaluationMessage);
                setTranscriptionFeedback(evaluationMessage, "is-error");
            });
    }

    async function runTranscriptTextEvaluation() {
        const transcriptText = transcriptionResultText?.value?.trim() || "";
        const shouldEvaluate = Boolean(transcriptionEvaluate?.checked);
        const checklistDraft = shouldEvaluate ? currentChecklistPayload() : null;

        if (transcriptText === "") {
            setTranscriptionFeedback("Вставте або відредагуйте текст транскрибації в правому полі, а потім запустіть окреме оцінювання.", "is-error");
            return;
        }

        if (!shouldEvaluate || !checklistDraft) {
            setTranscriptionFeedback("Увімкніть оцінювання за чек-листом і оберіть чек-лист для аналізу тексту.", "is-error");
            return;
        }

        setTranscriptionBusy(true, "evaluation");
        renderTranscriptionEvaluationPending(checklistDraft.name || transcriptionChecklist?.selectedOptions?.[0]?.textContent || "Чек-лист");
        setTranscriptionFeedback("Запускаємо окреме фонове оцінювання вже готового тексту транскрибації...", "is-loading");

        try {
            const evaluationPayload = await runTranscriptionEvaluation({
                text: transcriptText,
                dialogueText: transcriptText,
            }, checklistDraft);

            renderTranscriptionLlmMonitor(evaluationPayload.job || null);
            renderTranscriptionEvaluation(evaluationPayload.job?.evaluation || null, "");
            setTranscriptionFeedback(
                `Оцінювання тексту завершено. Чек-лист: ${checklistDraft.name || "—"}. Оцінка: ${evaluationPayload.job?.evaluation?.score ?? "--"}.`,
                "is-success"
            );
        } catch (evaluationError) {
            if (isAbortError(evaluationError) || isStoppingTranscriptionTasks) {
                return;
            }

            const evaluationMessage = evaluationError instanceof TypeError || String(evaluationError?.message || "").includes("Failed to fetch")
                ? "Не вдалося отримати статус фонового оцінювання тексту. Текст уже в полі, можна спробувати ще раз."
                : (evaluationError.message || "Не вдалося виконати оцінювання тексту транскрибації.");

            renderTranscriptionEvaluation(null, evaluationMessage);
            resetTranscriptionLlmMonitor(evaluationMessage);
            setTranscriptionFeedback(evaluationMessage, "is-error");
        } finally {
            setTranscriptionBusy(false);
        }
    }

    async function runTranscription() {
        const selectedFile = transcriptionFileInput?.files?.[0] || null;
        const audioUrlValue = transcriptionUrl?.value?.trim() || "";
        let transcriptionController = null;
        let transcriptionTaskId = "";

        if (!selectedFile && !audioUrlValue) {
            setTranscriptionFeedback("Додайте аудіофайл або вставте посилання на аудіо.", "is-error");
            return;
        }

        if (!selectedFile && audioUrlValue && looksLikeBinotelCabinetUrl(audioUrlValue)) {
            setTranscriptionFeedback("Посилання з кабінету Binotel веде на сторінку, а не на файл запису. Тут потрібне пряме посилання на аудіо або завантаження файла.", "is-error");
            return;
        }

        if (selectedFile && selectedFile.size > transcriptionServerUploadLimitBytes) {
            setTranscriptionFeedback(
                `Файл ${formatFileSize(selectedFile.size)} перевищує поточний серверний ліміт завантаження (${formatFileSize(transcriptionServerUploadLimitBytes)}). Потрібно або зменшити файл, або збільшити client_max_body_size в nginx.`,
                "is-error"
            );
            return;
        }

        const formData = new FormData();
        if (selectedFile) {
            formData.append("audio_file", selectedFile);
        }
        if (audioUrlValue) {
            formData.append("audio_url", audioUrlValue);
        }
        const activeCall = findCall(selectedCallId);
        const generalCallId = typeof activeCall?.generalCallId === "string"
            ? activeCall.generalCallId.trim()
            : "";
        formData.append("title", transcriptionTitle?.value?.trim() || "Розбір дзвінка менеджера");
        formData.append("language", transcriptionLanguage?.value || "auto");
        if (generalCallId) {
            formData.append("general_call_id", generalCallId);
        }
        const shouldEvaluate = Boolean(transcriptionEvaluate?.checked);
        const checklistDraft = shouldEvaluate ? currentChecklistPayload() : null;

        setTranscriptionBusy(true, "transcription");

        try {
            transcriptionTaskId = await createTranscriptionTask();
            activeTranscriptionTaskId = transcriptionTaskId;
            transcriptionController = new AbortController();
            activeTranscriptionRequestController = transcriptionController;
            syncTranscriptionStopButtonState();

            if (shouldEvaluate && checklistDraft) {
                renderTranscriptionEvaluationPending(checklistDraft.name || transcriptionChecklist?.selectedOptions?.[0]?.textContent || "Чек-лист");
            }

            setTranscriptionFeedback(
                selectedWhisperModel() === "large-v3"
                    ? `Завантажуємо аудіо та запускаємо faster-whisper large-v3${speakerDiarizationEnabled() ? " з speaker diarization" : ""}. На CPU це може зайняти кілька хвилин.`
                    : `Завантажуємо аудіо та запускаємо faster-whisper${speakerDiarizationEnabled() ? " з speaker diarization" : ""}...`,
                "is-loading"
            );

            formData.append("task_id", transcriptionTaskId);

            const response = await fetch(transcriptionEndpoint, {
                method: "POST",
                headers: {
                    Accept: "application/json"
                },
                signal: transcriptionController.signal,
                body: formData
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                if (response.status === 413) {
                    throw new Error(
                        `Файл перевищує поточний серверний ліміт завантаження (${formatFileSize(transcriptionServerUploadLimitBytes)}). Потрібно збільшити client_max_body_size в nginx.`
                    );
                }

                if (response.status === 504) {
                    throw new Error(
                        `Сервер не дочекався завершення транскрибації. Модель ${selectedWhisperModel()}${speakerDiarizationEnabled() ? " разом з speaker diarization" : ""} на поточному сервері обробляє запис довше, ніж дозволяє gateway timeout. Спробуйте medium/small або збільште timeout у web-проксі.`
                    );
                }

                throw new Error(payload.message || "Не вдалося виконати транскрибацію.");
            }

            const transcription = payload.transcription || {};
            const transcriptText = transcription?.dialogueText
                || payload.transcription?.formattedText
                || payload.transcription?.text
                || "faster-whisper не зміг розпізнати мовлення в цьому аудіо.";

            transcriptionResultText.value = transcriptText;
            if (!shouldEvaluate) {
                renderTranscriptionEvaluation(null);
                resetTranscriptionLlmMonitor("Транскрибацію виконано без запуску LLM-оцінювання.");
            }

            const detectedLanguage = languageLabel(transcription?.language || "auto");
            const sourceName = payload.task?.source?.name || "аудіо";
            const transcriptionModel = transcription?.model || transcriptionSettings?.transcription_model || "—";
            const diarizationApplied = Boolean(transcription?.speakerDiarization?.applied);
            const diarizationSuffix = diarizationApplied
                ? " Автор реплік розділено через pyannote."
                : (speakerDiarizationEnabled()
                    ? " Розділення автора реплік не спрацювало, тому використано резервне визначення."
                    : "");

            if (!shouldEvaluate || !checklistDraft) {
                setTranscriptionFeedback(`Готово: ${sourceName}. Модель: ${transcriptionModel}. Визначена мова: ${detectedLanguage}.${diarizationSuffix}`, "is-success");
                return;
            }

            setTranscriptionFeedback(
                `Транскрибацію завершено: ${sourceName}. Модель: ${transcriptionModel}. Визначена мова: ${detectedLanguage}.${diarizationSuffix} Запускаємо фонове оцінювання по чек-листу...`,
                "is-loading"
            );

            try {
                const evaluationPayload = await runTranscriptionEvaluation(transcription, checklistDraft);
                renderTranscriptionLlmMonitor(evaluationPayload.job || null);
                renderTranscriptionEvaluation(evaluationPayload.job?.evaluation || null, "");
                setTranscriptionFeedback(
                    `Готово: ${sourceName}. Модель: ${transcriptionModel}. Визначена мова: ${detectedLanguage}.${diarizationSuffix} Оцінка: ${evaluationPayload.job?.evaluation?.score ?? "--"}.`,
                    "is-success"
                );
            } catch (evaluationError) {
                if (isAbortError(evaluationError) || isStoppingTranscriptionTasks) {
                    return;
                }

                const evaluationMessage = evaluationError instanceof TypeError || String(evaluationError?.message || "").includes("Failed to fetch")
                    ? "Транскрибацію завершено, але не вдалося отримати статус фонового оцінювання. Текст дзвінка вже готовий, перевірте Ollama та спробуйте ще раз."
                    : (evaluationError.message || "Транскрибацію завершено, але оцінювання по чек-листу не вдалося.");

                renderTranscriptionEvaluation(null, evaluationMessage);
                resetTranscriptionLlmMonitor(evaluationMessage);
                setTranscriptionFeedback(evaluationMessage, "is-error");
            }
        } catch (error) {
            if (isAbortError(error) || isStoppingTranscriptionTasks) {
                return;
            }

            const message = error instanceof TypeError || String(error?.message || "").includes("Failed to fetch")
                ? "Сервер не відповів на запит транскрибації. Перевірте nginx/php-fpm після зміни лімітів і оновіть сторінку."
                : (error.message || "Не вдалося виконати транскрибацію.");

            if (shouldEvaluate) {
                renderTranscriptionEvaluation(null, "Оцінювання не виконано, тому що транскрибація завершилася помилкою.");
                resetTranscriptionLlmMonitor("LLM-оцінювання не запускалося, тому що транскрибація завершилася помилкою.");
            }

            setTranscriptionFeedback(message, "is-error");
        } finally {
            if (transcriptionTaskId !== "" && activeTranscriptionTaskId === transcriptionTaskId) {
                activeTranscriptionTaskId = null;
            }

            if (transcriptionController && activeTranscriptionRequestController === transcriptionController) {
                activeTranscriptionRequestController = null;
            }

            syncTranscriptionStopButtonState();
            setTranscriptionBusy(false);
        }
    }

    tableBody.addEventListener("click", (event) => {
        if (event.target.closest("[data-interaction-count-static]")) {
            return;
        }

        if (event.target.closest("[data-interaction-history-link]")) {
            return;
        }

        const row = event.target.closest("tr[data-call-id]");
        if (!row) {
            return;
        }

        const callId = Number(row.dataset.callId);
        const call = findCall(callId);
        if (!call) {
            return;
        }

        const actionButton = event.target.closest("[data-action]");
        const action = actionButton?.dataset.action || "";

        selectedCallId = callId;
        renderRows();

        if (!actionButton) {
            return;
        }

        if (action === "transcript") {
            openTranscriptModal(call);
            return;
        }

        if (action === "audio") {
            openAudioModal(call);
            return;
        }

        if (action === "score") {
            openScoreModal(call);
        }
    });

    navItems.forEach((item) => {
        item.addEventListener("click", (event) => {
            if (!item.dataset.sectionTarget) {
                return;
            }

            if (
                event.defaultPrevented
                || event.button !== 0
                || event.metaKey
                || event.ctrlKey
                || event.shiftKey
                || event.altKey
            ) {
                return;
            }

            event.preventDefault();
            activateSection(item.dataset.sectionTarget, { syncUrl: true });
        });
    });

    window.addEventListener("hashchange", () => {
        activateSection(window.location.hash, { syncUrl: false });
    });

    employeeFilterTrigger.addEventListener("click", () => {
        if (employeeFilterDropdown.hidden) {
            openEmployeeFilterDropdown();
            return;
        }

        closeEmployeeFilterDropdown();
    });

    employeeFilterDropdown.addEventListener("click", (event) => {
        const option = event.target.closest("[data-value]");
        if (!option) {
            return;
        }

        employeeFilter.value = option.dataset.value;
        employeeFilter.dispatchEvent(new Event("change", { bubbles: true }));
        closeEmployeeFilterDropdown();
    });

    employeeFilterBackdrop.addEventListener("click", () => {
        closeEmployeeFilterDropdown();
    });

    employeeFilter.addEventListener("change", (event) => {
        employeeFilterValue = event.target.value;
        callsPage = 1;
        syncEmployeeFilterDropdown();
        renderRows();
    });

    phoneSearch.addEventListener("input", (event) => {
        phoneSearchValue = event.target.value;
        callsPage = 1;
        renderRows();
    });

    transcriptionFileInput?.addEventListener("change", () => {
        activeTranscriptionComparisonRunId = "";
        syncTranscriptionFileName();
        setTranscriptionFeedback("Файл готовий до обробки.", "");
    });

    transcriptionResultText?.addEventListener("input", () => {
        syncTranscriptionAiRewriteControls();
        syncTranscriptionLlmControls();
    });

    checklistList?.addEventListener("click", (event) => {
        const deleteTrigger = event.target.closest("[data-checklist-delete-trigger]");
        if (deleteTrigger) {
            const deleteId = deleteTrigger.dataset.checklistDeleteTrigger;

            if (activeChecklistRenameId && activeChecklistRenameId !== deleteId) {
                pendingChecklistDeleteId = deleteId;
                pendingChecklistSelectionId = null;
                pendingChecklistRenameId = null;
                commitChecklistRename();
                return;
            }

            openChecklistDeleteModal(deleteId);
            return;
        }

        const renameTrigger = event.target.closest("[data-checklist-rename-trigger]");
        if (renameTrigger) {
            const renameId = renameTrigger.dataset.checklistRenameTrigger;

            if (activeChecklistRenameId && activeChecklistRenameId !== renameId) {
                pendingChecklistRenameId = renameId;
                pendingChecklistSelectionId = null;
                commitChecklistRename();
                return;
            }

            beginChecklistRename(renameId);
            return;
        }

        if (event.target.closest("[data-checklist-rename-input]")) {
            return;
        }

        const card = event.target.closest("[data-checklist-id]");
        if (!card) {
            return;
        }

        if (activeChecklistRenameId) {
            pendingChecklistSelectionId = card.dataset.checklistId;
            pendingChecklistRenameId = null;
            commitChecklistRename();
            return;
        }

        selectChecklist(card.dataset.checklistId, { syncDropdown: true });
        setChecklistFeedback("Редагуємо збережений чек-лист. Його пункти й бали можна відразу використати для оцінювання.", "");
    });

    checklistList?.addEventListener("keydown", (event) => {
        const renameInput = event.target.closest("[data-checklist-rename-input]");
        if (renameInput) {
            if (event.key === "Enter") {
                event.preventDefault();
                commitChecklistRename();
            }

            if (event.key === "Escape") {
                event.preventDefault();
                cancelChecklistRename();
            }

            return;
        }

        if (event.target.closest("[data-checklist-delete-trigger], [data-checklist-rename-trigger]")) {
            return;
        }

        const card = event.target.closest("[data-checklist-id]");
        if (!card) {
            return;
        }

        if (event.key === "F2") {
            event.preventDefault();
            beginChecklistRename(card.dataset.checklistId);
            return;
        }

        if (event.key !== "Enter" && event.key !== " ") {
            return;
        }

        event.preventDefault();

        if (activeChecklistRenameId) {
            pendingChecklistSelectionId = card.dataset.checklistId;
            pendingChecklistRenameId = null;
            commitChecklistRename();
            return;
        }

        selectChecklist(card.dataset.checklistId, { syncDropdown: true });
        setChecklistFeedback("Редагуємо збережений чек-лист. Його пункти й бали можна відразу використати для оцінювання.", "");
    });

    checklistList?.addEventListener("focusout", (event) => {
        const renameInput = event.target.closest("[data-checklist-rename-input]");
        if (!renameInput) {
            return;
        }

        setTimeout(() => {
            if (!activeChecklistRenameId || isChecklistRenameSaving) {
                return;
            }

            const activeElement = document.activeElement;
            if (activeElement && activeElement.closest("[data-checklist-rename-input]")) {
                return;
            }

            commitChecklistRename();
        }, 0);
    });

    checklistNewButton?.addEventListener("click", () => {
        resetChecklistEditor();
    });

    checklistSaveButton?.addEventListener("click", () => {
        saveChecklist();
    });

    checklistExportButton?.addEventListener("click", () => {
        openChecklistExportModal();
    });

    checklistExportClose?.addEventListener("click", () => closeChecklistExportModal());
    checklistExportChatGptButton?.addEventListener("click", () => exportChecklistTable("chatgpt"));
    checklistExportExcelButton?.addEventListener("click", () => exportChecklistTable("excel"));
    checklistExportGoogleButton?.addEventListener("click", () => exportChecklistTable("google"));

    checklistDuplicateButton?.addEventListener("click", () => {
        duplicateChecklist();
    });

    checklistName?.addEventListener("input", markChecklistEditorDirty);
    checklistType?.addEventListener("change", markChecklistEditorDirty);
    checklistPrompt?.addEventListener("input", markChecklistEditorDirty);
    checklistItemsEditor?.addEventListener("input", markChecklistEditorDirty);
    checklistItemsEditor?.addEventListener("change", markChecklistEditorDirty);
    checklistItemsEditor?.addEventListener("focusin", (event) => {
        const labelField = event.target.closest("[data-checklist-item-label]");
        if (!labelField) {
            return;
        }

        setChecklistItemLabelHeight(labelField, true);
    });
    checklistItemsEditor?.addEventListener("focusout", (event) => {
        const labelField = event.target.closest("[data-checklist-item-label]");
        if (!labelField) {
            return;
        }

        setTimeout(() => {
            if (document.activeElement === labelField) {
                return;
            }

            setChecklistItemLabelHeight(labelField, false);
        }, 0);
    });
    checklistItemsEditor?.addEventListener("input", (event) => {
        const labelField = event.target.closest("[data-checklist-item-label]");
        if (!labelField) {
            return;
        }

        setChecklistItemLabelHeight(labelField, labelField === document.activeElement);
    });

    checklistItemsEditor?.addEventListener("click", (event) => {
        const addButton = event.target.closest("[data-checklist-item-add]");
        if (!addButton) {
            return;
        }

        const currentRows = getChecklistEditorItems({ keepEmpty: true });
        const parentRow = addButton.closest("[data-checklist-item-row]");
        const index = Math.max(0, Number(parentRow?.dataset.index ?? currentRows.length - 1));
        currentRows.splice(index + 1, 0, { label: "", max_points: 10 });
        renderChecklistItemsEditor(currentRows);
        markChecklistEditorDirty();

        const nextLabelInput = checklistItemsEditor.querySelector(`[data-index="${index + 1}"] [data-checklist-item-label]`);
        nextLabelInput?.focus();
    });
    checklistItemsEditor?.addEventListener("dragstart", (event) => {
        const handle = event.target.closest("[data-checklist-item-drag-handle]");
        if (!handle) {
            return;
        }

        const row = handle.closest("[data-checklist-item-row]");
        if (!row) {
            return;
        }

        draggedChecklistItemRow = row;
        didReorderChecklistItems = false;
        row.classList.add("is-dragging");
        clearChecklistItemDropMarker();

        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = "move";
            event.dataTransfer.setData("text/plain", row.dataset.index || "");
        }
    });
    checklistItemsEditor?.addEventListener("dragover", (event) => {
        if (!draggedChecklistItemRow) {
            return;
        }

        const targetRow = event.target.closest("[data-checklist-item-row]");
        if (!targetRow || targetRow === draggedChecklistItemRow) {
            clearChecklistItemDropMarker();
            return;
        }

        event.preventDefault();

        if (event.dataTransfer) {
            event.dataTransfer.dropEffect = "move";
        }

        const bounds = targetRow.getBoundingClientRect();
        const insertAfter = event.clientY > (bounds.top + (bounds.height / 2));
        setChecklistItemDropMarker(targetRow, insertAfter);
    });
    checklistItemsEditor?.addEventListener("drop", (event) => {
        if (!draggedChecklistItemRow) {
            return;
        }

        event.preventDefault();

        const targetRow = checklistDropTargetRow || event.target.closest("[data-checklist-item-row]");
        if (!targetRow || targetRow === draggedChecklistItemRow) {
            finishChecklistItemDrag();
            return;
        }

        didReorderChecklistItems = moveChecklistItemRow(
            draggedChecklistItemRow,
            targetRow,
            checklistDropInsertAfter
        ) || didReorderChecklistItems;
        finishChecklistItemDrag();
    });
    checklistItemsEditor?.addEventListener("dragend", () => {
        finishChecklistItemDrag();
    });

    syncChecklistItemLabelHeights();

    transcriptionChecklist?.addEventListener("change", (event) => {
        selectChecklist(event.target.value, { syncDropdown: false });
        setChecklistFeedback("У транскрибації вибрано цей чек-лист. Його пункти й бали підуть у промпт під час оцінювання.", "");
    });

    transcriptionEvaluate?.addEventListener("change", () => {
        syncTranscriptionChecklistState();
        syncTranscriptionLlmControls();
        if (transcriptionEvaluate.checked) {
            setTranscriptionFeedback("Оцінювання за чек-листом увімкнено.", "");
            return;
        }

        setTranscriptionFeedback("Оцінювання вимкнено. Буде повернено тільки текст транскрибації.", "");
    });

    transcriptionRunButton?.addEventListener("click", () => {
        runTranscription();
    });

    transcriptionStopButton?.addEventListener("click", () => {
        stopTranscriptionTasks();
    });

    transcriptionScoreDetailsToggle?.addEventListener("click", () => {
        toggleTranscriptionEvaluationDetails();
    });

    transcriptionLlmPromptToggle?.addEventListener("click", () => {
        toggleTranscriptionLlmPrompt();
    });

    transcriptionLlmEvaluateButton?.addEventListener("click", () => {
        runTranscriptTextEvaluation();
    });

    transcriptionLlmSettingsButton?.addEventListener("click", () => {
        openTranscriptionLlmSettingsModal();
    });

    settingsLlmTemperature?.addEventListener("input", () => {
        syncLlmSettingsSliderValues();
    });

    settingsLlmNumCtx?.addEventListener("input", () => {
        syncLlmSettingsSliderValues();
    });

    settingsWhisperInitialPrompt?.addEventListener("paste", handleWhisperInitialPromptPaste);

    [
        settingsApiUrl,
        settingsApiKey,
        settingsProvider,
        settingsModel,
        settingsLlmThinkingEnabled,
        settingsLlmTemperature,
        settingsLlmNumCtx,
        settingsLlmTopK,
        settingsLlmTopP,
        settingsLlmRepeatPenalty,
        settingsLlmNumPredict,
        settingsLlmSeed,
        settingsLlmTimeoutSeconds,
        settingsWhisperModel,
        settingsWhisperInitialPrompt,
        settingsSpeakerDiarizationEnabled,
        settingsSpeakerDiarizationToken,
    ].forEach((element) => {
        element?.addEventListener("input", markSettingsDirty);
        element?.addEventListener("change", markSettingsDirty);
    });

    settingsSaveButton?.addEventListener("click", () => {
        saveTranscriptionSettings();
    });

    settingsCheckConnectionButton?.addEventListener("click", () => {
        const summary = [
            settingsLogin?.value?.trim() ? `Логін: ${settingsLogin.value.trim()}` : null,
            settingsApiUrl?.value?.trim() ? `API URL: ${settingsApiUrl.value.trim()}` : null,
            settingsWhisperModel?.value?.trim() ? `faster-whisper: ${settingsWhisperModel.value.trim()}` : null,
            settingsWhisperInitialPrompt?.value?.trim() ? "Initial prompt: додано" : "Initial prompt: порожній",
            `Автор реплік: ${speakerDiarizationEnabled() ? "увімкнено" : "вимкнено"}`,
            hasSpeakerDiarizationToken() ? "Hugging Face token: додано" : "Hugging Face token: відсутній",
        ].filter(Boolean).join(" | ");

        setSettingsFeedback(
            summary !== ""
                ? `Форма доступна. Поточні значення: ${summary}. LLM-параметри налаштовуються окремо біля AI-блоків.`
                : "Форма доступна. LLM-параметри налаштовуються окремо біля AI-блоків.",
            "is-success"
        );
    });

    document.addEventListener("click", (event) => {
        if (!employeeFilterField.contains(event.target)) {
            closeEmployeeFilterDropdown();
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            closeEmployeeFilterDropdown();
        }
    });

    dateRangeTrigger.addEventListener("click", () => {
        if (datePicker.hidden) {
            openDatePicker();
            return;
        }

        closeDatePicker();
    });

    calendarPrev.addEventListener("click", () => {
        calendarViewDate = new Date(calendarViewDate.getFullYear(), calendarViewDate.getMonth() - 1, 1);
        renderCalendar();
    });

    calendarNext.addEventListener("click", () => {
        calendarViewDate = new Date(calendarViewDate.getFullYear(), calendarViewDate.getMonth() + 1, 1);
        renderCalendar();
    });

    [monthGridA, monthGridB].forEach((grid) => {
        grid.addEventListener("click", (event) => {
            const button = event.target.closest("[data-calendar-date]");
            if (!button) {
                return;
            }

            handleCalendarDateSelect(button.dataset.calendarDate);
        });
    });

    dateCancel.addEventListener("click", () => {
        closeDatePicker();
    });

    dateApply.addEventListener("click", () => {
        if (!draftRangeStart && !draftRangeEnd) {
            rangeStart = null;
            rangeEnd = null;
            hasCustomDateRange = false;
        } else {
            const safeEnd = draftRangeEnd || draftRangeStart;
            rangeStart = normalizeDate(draftRangeStart);
            rangeEnd = normalizeDate(safeEnd);
            hasCustomDateRange = true;
        }

        closeDatePicker();
        callsPage = 1;
        managersPage = 1;
        renderRows();
        renderManagersRows();
    });

    [interactionCountSort, interactionNumberSort, modelSort, durationSort, timeSort, scoreSort].forEach((button) => {
        button.addEventListener("click", () => {
            const nextField = button.dataset.sortField;

            if (sortField === nextField) {
                sortDirection = sortDirection === "desc" ? "asc" : "desc";
            } else {
                sortField = nextField;
                sortDirection = "desc";
            }

            callsPage = 1;
            renderRows();
        });
    });

    callsPagination.addEventListener("click", (event) => {
        const button = event.target.closest("[data-page]");
        if (!button || button.disabled) {
            return;
        }

        callsPage = Number(button.dataset.page);
        renderRows();
    });

    managersPagination.addEventListener("click", (event) => {
        const button = event.target.closest("[data-page]");
        if (!button || button.disabled) {
            return;
        }

        managersPage = Number(button.dataset.page);
        renderManagersRows();
    });

    callsTable?.addEventListener("pointerdown", startCallsTableColumnResize);
    callsTable?.addEventListener("dblclick", (event) => {
        if (!event.target.closest("[data-call-column-resizer]")) {
            return;
        }

        event.preventDefault();
        resetCallsTableColumnWidths();
    });
    modalClose.addEventListener("click", closeModal);
    checklistDeleteClose?.addEventListener("click", () => closeChecklistDeleteModal());
    checklistDeleteCancelButton?.addEventListener("click", () => closeChecklistDeleteModal());
    checklistDeleteConfirmButton?.addEventListener("click", () => {
        confirmChecklistDelete();
    });
    transcriptionAiSettingsButton?.addEventListener("click", () => openTranscriptionAiSettingsModal());
    transcriptionAiRewriteButton?.addEventListener("click", () => {
        runTranscriptionAiRewrite();
    });
    transcriptionAiStopButton?.addEventListener("click", () => {
        stopTranscriptionAiRewrite();
    });
    transcriptionAiLiveCloseButton?.addEventListener("click", () => {
        closeTranscriptionAiLiveBox();
    });
    transcriptionAiInputCloseButton?.addEventListener("click", () => {
        closeTranscriptionAiInputBox();
    });
    transcriptionAiSettingsClose?.addEventListener("click", () => closeTranscriptionAiSettingsModal());
    transcriptionAiSettingsCancelButton?.addEventListener("click", () => closeTranscriptionAiSettingsModal());
    transcriptionAiSettingsSaveButton?.addEventListener("click", () => {
        if (saveTranscriptionAiSettings()) {
            closeTranscriptionAiSettingsModal();
        }
    });

    transcriptionLlmSettingsClose?.addEventListener("click", () => closeTranscriptionLlmSettingsModal());
    transcriptionLlmSettingsCancelButton?.addEventListener("click", () => closeTranscriptionLlmSettingsModal());
    transcriptionLlmSettingsSaveButton?.addEventListener("click", () => {
        if (saveTranscriptionLlmSettings()) {
            closeTranscriptionLlmSettingsModal();
        }
    });

    transcriptionAiTemperature?.addEventListener("input", () => {
        syncTranscriptionAiGenerationSliderValues();
    });

    transcriptionAiNumCtx?.addEventListener("input", () => {
        syncTranscriptionAiGenerationSliderValues();
    });

    transcriptionLlmTemperature?.addEventListener("input", () => {
        syncTranscriptionLlmGenerationSliderValues();
    });

    transcriptionLlmNumCtx?.addEventListener("input", () => {
        syncTranscriptionLlmGenerationSliderValues();
    });

    transcriptionAiModel?.addEventListener("change", () => {
        switchTranscriptionAiSettingsModel(transcriptionAiModel.value);
    });

    transcriptionLlmModel?.addEventListener("change", () => {
        switchTranscriptionLlmSettingsModel(transcriptionLlmModel.value);
    });

    transcriptionAiResetModelSettingsButton?.addEventListener("click", () => {
        resetTranscriptionAiCurrentModelSettingsToDefaults();
    });

    transcriptionLlmResetModelSettingsButton?.addEventListener("click", () => {
        resetTranscriptionLlmCurrentModelSettingsToDefaults();
    });

    callModal.addEventListener("click", (event) => {
        if (event.target === callModal) {
            closeModal();
        }
    });

    checklistDeleteModal?.addEventListener("click", (event) => {
        if (event.target === checklistDeleteModal) {
            closeChecklistDeleteModal();
        }
    });

    checklistExportModal?.addEventListener("click", (event) => {
        if (event.target === checklistExportModal) {
            closeChecklistExportModal();
        }
    });

    transcriptionAiSettingsModal?.addEventListener("click", (event) => {
        if (event.target === transcriptionAiSettingsModal) {
            closeTranscriptionAiSettingsModal();
        }
    });

    transcriptionLlmSettingsModal?.addEventListener("click", (event) => {
        if (event.target === transcriptionLlmSettingsModal) {
            closeTranscriptionLlmSettingsModal();
        }
    });

    document.addEventListener("click", (event) => {
        if (!datePicker.hidden && !event.target.closest(".filter-date-field")) {
            closeDatePicker();
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && !datePicker.hidden) {
            closeDatePicker();
            return;
        }

        if (event.key === "Escape" && !callModal.hidden) {
            closeModal();
            return;
        }

        if (event.key === "Escape" && !checklistDeleteModal.hidden) {
            closeChecklistDeleteModal();
            return;
        }

        if (event.key === "Escape" && !checklistExportModal.hidden) {
            closeChecklistExportModal();
            return;
        }

        if (event.key === "Escape" && !transcriptionLlmSettingsModal.hidden) {
            closeTranscriptionLlmSettingsModal();
            return;
        }

        if (event.key === "Escape" && !transcriptionAiSettingsModal.hidden) {
            closeTranscriptionAiSettingsModal();
        }
    });

    if (!isInteractionHistoryMode) {
        initializeCallsTableColumnWidths();
    }
    rebuildInteractionCountIndex();
    syncCallsState({ preserveCustomRange: false });
    applyInteractionHistoryMode();

    renderFilterOptions();
    renderDateRangeSummary();
    activeChecklistId = resolveExistingChecklistId(
        activeChecklistId,
        storedChecklistSelectionId,
        defaultChecklistId,
        checklists[0]?.id || null
    );

    renderRows();
    renderManagersRows();
    renderChecklistList();
    syncTranscriptionChecklistOptions(activeChecklistId || defaultChecklistId || checklists[0]?.id || "");
    if (activeChecklistId) {
        selectChecklist(activeChecklistId, { syncDropdown: true });
    } else {
        writeStoredChecklistSelectionId("");
    }
    syncSettingsFormFromState();
    syncTranscriptionFileName();
    syncTranscriptionChecklistState();
    syncTranscriptionAiRewriteControls();
    syncTranscriptionLlmControls();
    resetTranscriptionAiLiveState();
    resetTranscriptionAiInputState();
    hideTranscriptionAiLiveBox();
    hideTranscriptionAiInputBox();
    renderTranscriptionEvaluation(null);
    resetTranscriptionLlmMonitor();
    syncTranscriptionStopButtonState();
    if (isEvaluationJobActive(initialActiveEvaluationJob)) {
        resumeActiveEvaluationJob(
            initialActiveEvaluationJob,
            "На сторінці вже було активне фонове оцінювання. Відновлюємо моніторинг поточного завдання."
        );
    }
    activateSection(window.location.hash, { syncUrl: false });
    applyForcedTranscriptionRequest().catch((error) => {
        const message = error instanceof TypeError || String(error?.message || "").includes("Failed to fetch")
            ? "Не вдалося автоматично підготувати дзвінок для Транскрибації 1."
            : (error.message || "Не вдалося перенести дзвінок у Транскрибацію 1.");

        setTranscriptionFeedback(message, "is-error");
    });

    document.addEventListener("visibilitychange", () => {
        if (document.visibilityState === "visible") {
            refreshPageBootstrap();
        }
    });

    window.addEventListener("focus", () => {
        if (document.visibilityState === "visible") {
            refreshPageBootstrap();
        }
    });

    window.addEventListener("pageshow", (event) => {
        if (event.persisted) {
            refreshPageBootstrap({ force: true });
        }
    });
</script>
</body>
</html>
