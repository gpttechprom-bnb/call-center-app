@extends('auth.layout')

@section('title', 'Реєстрація')

@section('content')
    <div class="auth-kicker">Реєстрація</div>
    <h1>Створити акаунт</h1>
    <p>Заповніть дані, щоб підготувати доступ до системи контролю якості дзвінків.</p>

    <form class="auth-form" method="get" action="{{ route('call-center') }}">
        <div class="form-grid">
            <div class="form-field">
                <label for="registerName">Ім'я</label>
                <input id="registerName" type="text" placeholder="Ім'я менеджера">
            </div>
            <div class="form-field">
                <label for="registerEmail">Email</label>
                <input id="registerEmail" type="email" placeholder="manager@yaprofi.ua">
            </div>
        </div>

        <div class="form-grid">
            <div class="form-field">
                <label for="registerPassword">Пароль</label>
                <input id="registerPassword" type="password" placeholder="••••••••">
            </div>
            <div class="form-field">
                <label for="registerPasswordConfirm">Повторіть пароль</label>
                <input id="registerPasswordConfirm" type="password" placeholder="••••••••">
            </div>
        </div>

        <div class="auth-row">
            <button class="auth-button" type="submit">Зареєструватися</button>
            <a class="auth-secondary" href="{{ route('login') }}">У мене вже є акаунт</a>
        </div>
    </form>

    <div class="auth-note">
        Це UI-заготовка сторінки реєстрації. За наступний крок можемо підключити збереження користувачів і справжній вхід.
    </div>
@endsection
