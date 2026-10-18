<?php

return [
    /*
    | Named routes exposed to the frontend through @routes, so Vue code can call
    | route('assignments.create') instead of hard-coding URLs. Keep internal and
    | tooling routes out of the page source.
    */
    'except' => ['api.*', 'ignition.*', 'sanctum.*', 'debugbar.*'],
];
