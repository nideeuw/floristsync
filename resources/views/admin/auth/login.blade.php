<x-login-card title="Staff sign in"
    subtitle="Sign in to manage orders, products and reports."
    :action="route('admin.login.store')"
    :dark="true"
    badge="Staff area"
    headline="Everything your shop needs, in one place."
    :points="[
        ['icon' => 'shopping-bag', 'text' => 'Track and fulfil online orders'],
        ['icon' => 'package', 'text' => 'Keep stock and catalog up to date'],
        ['icon' => 'trending-up', 'text' => 'Review sales and reports'],
    ]" />
