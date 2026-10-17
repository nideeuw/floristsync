<x-error-page
    :code="429"
    title="Too Many Requests"
    :message="(isset($exception) && $exception?->getMessage()) ? $exception->getMessage() : 'You have made too many requests. Please slow down and try again shortly.'"
    icon="hourglass"
/>
