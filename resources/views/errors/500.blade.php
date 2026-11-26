@include('errors.layout', [
    'code' => '500',
    'title' => __('Something went wrong'),
    'message' => __('The server hit a problem. Try again in a moment, and tell the platform administrator if it keeps happening.'),
])
