<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Activity Log Queue Mode
    |--------------------------------------------------------------------------
    |
    | Set true to push activity logs to queue for high-throughput systems.
    | Keep false for synchronous writes on smaller projects.
    |
    */
    'queue' => env('ACTIVITY_LOG_QUEUE', false),

    /*
    |--------------------------------------------------------------------------
    | Strict Admin Action Mapping
    |--------------------------------------------------------------------------
    |
    | Key format:
    | - "method:<ControllerMethodName>" exact method mapping
    | - "route:<route.name>" exact route-name mapping
    | - "path:<uri-fragment>" prefix/contains style matching
    |
    | Example:
    | 'method:rechargeWalletFromAdmin' => 'wallet_recharged',
    |
    */
    'strict_action_map' => [
        'method:rechargeWalletFromAdmin' => 'wallet_recharged',
        'method:blockUserFromAdmin' => 'blocked',
        'method:unblockUserFromAdmin' => 'unblocked',
        'method:banDoctor' => 'banned',
        'method:activateDoctor' => 'activated',
        'method:deleteTopHospitals' => 'deleted',
        'method:editTopHospitals' => 'updated',
        'method:addTopHospitals' => 'created',
        'method:editCommonHealthProblems' => 'updated',
        'method:addCommonHealthProblems' => 'created',
        'method:deleteCommonHealthProblems' => 'deleted',
        'path:logout' => 'logout',
    ],
];
