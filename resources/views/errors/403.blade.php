<x-error-page
    :code="403"
    title="Access Forbidden"
    :message="(isset($exception) && $exception?->getMessage()) ? $exception->getMessage() : 'You do not have permission to access this page or resource.'"
    icon="shield-alert"
/>
