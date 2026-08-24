@include('errors.layout', [
    'code'     => '503',
    'eyebrow'  => __('Temporarily unavailable'),
    'headline' => __('We\'ll be right back'),
    'body'     => __(':brand is briefly offline for maintenance. Please check back shortly.', ['brand' => rescue(fn () => function_exists('site_name') ? site_name() : config('app.name'), config('app.name', 'InstaMagic'), false)]),
])
