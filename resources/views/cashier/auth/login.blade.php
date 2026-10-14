<x-login-card title="Cashier sign in"
    subtitle="Sign in to open your shift and start selling."
    :action="route('cashier.login.store')"
    button="Open terminal"
    badge="Cashier terminal"
    headline="Fast, friendly checkout at the counter."
    :points="[
        ['icon' => 'zap', 'text' => 'Quick sales with product search'],
        ['icon' => 'wallet', 'text' => 'Cash, QRIS and debit payments'],
        ['icon' => 'clock', 'text' => 'Your shift opens on login'],
    ]" />
