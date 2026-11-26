@include('errors.layout', [
    'code' => $exception->getStatusCode(),
    'title' => __('Request not accepted'),
    'message' => __('The request could not be completed. Go back and try again.'),
])
