<?php

return [
    'lookahead_days' => 90,
    'alerts' => [
        'age_70' => [
            'label' => 'Đủ 70 tuổi',
            'age' => 70,
            'type' => 'reached',
            'purpose' => 'Rà soát chính sách BHYT',
            'message' => 'Có %d người từ 70 tuổi trở lên cần rà soát chính sách BHYT.',
        ],
        'age_75' => [
            'label' => 'Đủ 75 tuổi',
            'age' => 75,
            'type' => 'reached',
            'purpose' => 'Rà soát trợ cấp xã hội',
            'exclude_if_flag' => 'social_assistance',
            'message' => 'Có %d người từ 75 tuổi trở lên cần rà soát chính sách trợ cấp.',
        ],
        'upcoming_70' => [
            'label' => 'Sắp đủ 70 tuổi',
            'age' => 70,
            'type' => 'upcoming',
            'purpose' => 'Sắp đến tuổi rà soát BHYT',
            'message' => 'Có %d người sẽ đủ 70 tuổi trong vòng 90 ngày.',
        ],
        'upcoming_75' => [
            'label' => 'Sắp đủ 75 tuổi',
            'age' => 75,
            'type' => 'upcoming',
            'purpose' => 'Sắp đến tuổi rà soát trợ cấp',
            'message' => 'Có %d người sẽ đủ 75 tuổi trong vòng 90 ngày.',
        ],
    ],
];
