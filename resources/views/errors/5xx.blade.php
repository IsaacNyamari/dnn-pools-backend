@include('errors.layout', [
    'code' => $exception->getStatusCode(),
    'title' => __('Something went wrong'),
    'message' => __('The server hit a problem. Try again in a moment.'),
])
