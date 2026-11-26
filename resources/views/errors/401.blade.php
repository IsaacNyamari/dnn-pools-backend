@include('errors.layout', [
    'code' => '401',
    'title' => __('Sign in required'),
    'message' => __('You need to sign in to see this page.'),
])
