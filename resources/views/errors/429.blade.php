@include('errors.layout', [
    'code'     => '429',
    'eyebrow'  => __('Too many requests'),
    'headline' => __('Please slow down'),
    'body'     => __('You\'ve made a lot of requests in a short time. Wait a moment and try again.'),
])
