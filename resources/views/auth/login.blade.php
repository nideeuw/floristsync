<x-login-card title="FloristSync"
    subtitle="Sign in to your account"
    :action="route('login')"
    :home="route('home')"
    :forgot="Route::has('password.request') ? route('password.request') : null">

    <p class="mt-4 text-center text-sm">
        New here?
        <a href="{{ route('register') }}" class="font-semibold text-moss hover:text-ink">Create an account</a>
    </p>
</x-login-card>
