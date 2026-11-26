@include('errors.layout', [
    'code' => '403',
    'title' => __('Access denied'),
    'message' => __('Your account is not allowed to see this page. If you think it should be, ask your director or the platform administrator.'),
])
