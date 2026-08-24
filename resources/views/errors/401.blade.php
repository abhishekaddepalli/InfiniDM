@include('errors.layout', [
    'code'     => '401',
    'eyebrow'  => __('Not authorized'),
    'headline' => __('You need to sign in'),
    'body'     => __('This page requires you to be signed in. Please log in and try again.'),
])
