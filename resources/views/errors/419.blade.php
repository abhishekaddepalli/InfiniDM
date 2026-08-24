@include('errors.layout', [
    'code'     => '419',
    'eyebrow'  => __('Session expired'),
    'headline' => __('Your session timed out'),
    'body'     => __('For your security the page expired. Please refresh and try again.'),
    'ctaUrl'   => url()->previous(),
    'ctaLabel' => __('Try again'),
])
