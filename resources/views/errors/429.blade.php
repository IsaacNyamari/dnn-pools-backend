@include('errors.layout', [
    'code' => '429',
    'title' => __('Too many requests'),
    'message' => __('You have tried too many times. Wait a minute and try again.'),
])
