<x-error-page
    :code="404"
    title="Page Not Found"
    :message="(isset($exception) && $exception?->getMessage()) ? $exception->getMessage() : 'The page you are looking for could not be found or has been moved.'"
    icon="compass"
/>
