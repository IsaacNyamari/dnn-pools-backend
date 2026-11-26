@include('errors.layout', [
    'code' => '404',
    'title' => __('Page not found'),
    'message' => __('The page does not exist or has moved. Check the address and try again.'),
])
