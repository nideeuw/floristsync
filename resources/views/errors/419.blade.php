<x-error-page
    :code="419"
    title="Page Expired"
    :message="(isset($exception) && $exception?->getMessage()) ? $exception->getMessage() : 'Your session has expired due to inactivity. Please refresh and try again.'"
    icon="clock"
    :is-expired="true"
/>
