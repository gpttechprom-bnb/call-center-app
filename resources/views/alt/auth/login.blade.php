@extends('alt.auth.layout')

@section('title', 'Вхід')

@section('content')
    <div class="auth-kicker">Вхід</div>
    <h1>Увійти в кабінет</h1>
    <p>Введіть логін та пароль, щоб перейти до аналітики дзвінків і панелі оцінювання.</p>

    <form class="auth-form" method="get" action="{{ route('alt.call-center') }}">
        <div class="form-field">
            <label for="loginEmail">Email або логін</label>
            <input id="loginEmail" type="text" placeholder="manager@yaprofi.ua">
        </div>
        <div class="form-field">
            <label for="loginPassword">Пароль</label>
            <input id="loginPassword" type="password" placeholder="••••••••">
        </div>

        <div class="auth-row">
            <button class="auth-button" type="submit">Увійти</button>
            <a class="auth-secondary" href="{{ route('alt.call-center') }}">Перейти в alt-панель</a>
        </div>
    </form>

    <div class="auth-switch">
        Ще немає акаунта?
        <a href="{{ route('alt.register') }}">Створити обліковий запис</a>
    </div>

    <div class="auth-note">
        Це підготовлена UI-сторінка входу. За потреби далі підключимо реальну авторизацію і валідацію.
    </div>
@endsection
