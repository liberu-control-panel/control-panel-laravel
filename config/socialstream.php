<?php

use JoelButcher\Socialstream\Providers;

return [
    'middleware' => ['web'],
    'prompt' => 'Or Login Via',
    'providers' => [
        Providers::github(),
        Providers::gitlab(),
    ],
    'component' => 'socialstream::components.socialstream',
];
