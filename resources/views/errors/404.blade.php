@include('errors.layout', [
    'code'     => '404',
    'eyebrow'  => __('Page not found'),
    'headline' => __('This page doesn\'t exist'),
    'body'     => __('The page you\'re looking for may have moved, been removed, or the link is broken.'),
])
