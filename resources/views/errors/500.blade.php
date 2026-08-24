@include('errors.layout', [
    'code'     => '500',
    'eyebrow'  => __('Server error'),
    'headline' => __('Something went wrong'),
    'body'     => __('An unexpected error occurred on our end. Please try again in a few moments.'),
])
