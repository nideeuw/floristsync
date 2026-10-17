<x-error-page
    :code="503"
    title="Service Unavailable"
    :message="(isset($exception) && $exception?->getMessage()) ? $exception->getMessage() : 'We are performing routine maintenance. Please check back shortly.'"
    icon="sparkles"
/>
