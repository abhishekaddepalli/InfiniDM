@include('errors.layout', [
    'code'     => '403',
    'eyebrow'  => __('Access denied'),
    'headline' => __('You don\'t have permission'),
    'body'     => __('You are not allowed to view this page. If you think this is a mistake, contact your administrator.'),
])
