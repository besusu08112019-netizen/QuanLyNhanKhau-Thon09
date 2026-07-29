<?php

return [
    'lookahead_days' => \App\Policies\AgePolicy::UPCOMING_POLICY_LOOKAHEAD_DAYS,
    'alerts' => [
        'age_70' => [
            'label' => 'Đủ ' . \App\Config\CitizenPolicyDefaults::BHYT_DEFAULT_AGE . ' tuổi',
            'age' => \App\Config\CitizenPolicyDefaults::BHYT_DEFAULT_AGE,
            'type' => 'reached',
            'purpose' => 'Rà soát chính sách BHYT',
            'message' => 'Có %d người từ ' . \App\Config\CitizenPolicyDefaults::BHYT_DEFAULT_AGE . ' tuổi trở lên cần rà soát chính sách BHYT.',
        ],
        'age_75' => [
            'label' => 'Đủ ' . \App\Config\CitizenPolicyDefaults::SOCIAL_ALLOWANCE_DEFAULT_AGE . ' tuổi',
            'age' => \App\Config\CitizenPolicyDefaults::SOCIAL_ALLOWANCE_DEFAULT_AGE,
            'type' => 'reached',
            'purpose' => 'Rà soát trợ cấp xã hội',
            'exclude_if_flag' => 'social_assistance',
            'message' => 'Có %d người từ ' . \App\Config\CitizenPolicyDefaults::SOCIAL_ALLOWANCE_DEFAULT_AGE . ' tuổi trở lên cần rà soát chính sách trợ cấp.',
        ],
        'upcoming_70' => [
            'label' => 'Sắp đủ ' . \App\Config\CitizenPolicyDefaults::BHYT_DEFAULT_AGE . ' tuổi',
            'age' => \App\Config\CitizenPolicyDefaults::BHYT_DEFAULT_AGE,
            'type' => 'upcoming',
            'purpose' => 'Sắp đến tuổi rà soát BHYT',
            'message' => 'Có %d người sẽ đủ ' . \App\Config\CitizenPolicyDefaults::BHYT_DEFAULT_AGE . ' tuổi trong vòng ' . \App\Policies\AgePolicy::UPCOMING_POLICY_LOOKAHEAD_DAYS . ' ngày.',
        ],
        'upcoming_75' => [
            'label' => 'Sắp đủ ' . \App\Config\CitizenPolicyDefaults::SOCIAL_ALLOWANCE_DEFAULT_AGE . ' tuổi',
            'age' => \App\Config\CitizenPolicyDefaults::SOCIAL_ALLOWANCE_DEFAULT_AGE,
            'type' => 'upcoming',
            'purpose' => 'Sắp đến tuổi rà soát trợ cấp',
            'message' => 'Có %d người sẽ đủ ' . \App\Config\CitizenPolicyDefaults::SOCIAL_ALLOWANCE_DEFAULT_AGE . ' tuổi trong vòng ' . \App\Policies\AgePolicy::UPCOMING_POLICY_LOOKAHEAD_DAYS . ' ngày.',
        ],
    ],
];
