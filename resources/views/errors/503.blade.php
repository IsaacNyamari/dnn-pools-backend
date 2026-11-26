@include('errors.layout', [
    'code' => '503',
    'title' => __('Down for maintenance'),
    'message' => __('The system is being updated. Try again shortly.'),
    'showHomeLink' => false,
])
