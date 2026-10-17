<x-error-page
    :code="500"
    title="Server Error"
    :message="(app()->hasDebugModeEnabled() && isset($exception) && $exception?->getMessage()) ? $exception->getMessage() : 'An unexpected error occurred on our server. Please try again later.'"
    icon="alert-octagon"
/>
