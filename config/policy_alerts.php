<?php

return [
    'lookahead_days' => \App\Policies\AgePolicy::UPCOMING_POLICY_LOOKAHEAD_DAYS,
    'alerts' => [
        'age_70' => [
            'label' => "\u{0110}\u{1EE7} " . \App\Policies\InsurancePolicy::DEFAULT_AGE . " tu\u{1ED5}i",
            'age' => \App\Policies\InsurancePolicy::DEFAULT_AGE,
            'type' => 'reached',
            'purpose' => "RÃ  soÃ¡t chÃ­nh sÃ¡ch BHYT",
            'exclude_if_field' => 'health_insurance_group',
            'exclude_if_values' => ["Ng\u{01B0}\u{1EDD}i cao tu\u{1ED5}i", "\u{0110}\u{1ED1}i t\u{01B0}\u{1EE3}ng kh\u{00E1}c"],
            'exclude_if_prefixes' => ["Ng\u{01B0}\u{1EDD}i cao tu\u{1ED5}i", "\u{0110}\u{1ED1}i t\u{01B0}\u{1EE3}ng kh\u{00E1}c"],
            'message' => "C\u{00F3} %d ng\u{01B0}\u{1EDD}i t\u{1EEB} " . \App\Policies\InsurancePolicy::DEFAULT_AGE . " tu\u{1ED5}i tr\u{1EDF} l\u{00EA}n ch\u{01B0}a chuy\u{1EC3}n b\u{1EA3}o hi\u{1EC3}m ng\u{01B0}\u{1EDD}i cao tu\u{1ED5}i.",
        ],
        'age_75' => [
            'label' => "\u{0110}\u{1EE7} " . \App\Config\CitizenPolicyDefaults::SOCIAL_ALLOWANCE_DEFAULT_AGE . " tu\u{1ED5}i",
            'age' => \App\Config\CitizenPolicyDefaults::SOCIAL_ALLOWANCE_DEFAULT_AGE,
            'type' => 'reached',
            'purpose' => "R\u{00E0} so\u{00E1}t tr\u{1EE3} c\u{1EA5}p x\u{00E3} h\u{1ED9}i",
            'exclude_if_flag' => 'social_assistance',
            'message' => "C\u{00F3} %d ng\u{01B0}\u{1EDD}i t\u{1EEB} " . \App\Config\CitizenPolicyDefaults::SOCIAL_ALLOWANCE_DEFAULT_AGE . " tu\u{1ED5}i tr\u{1EDF} l\u{00EA}n ch\u{01B0}a h\u{01B0}\u{1EDF}ng tr\u{1EE3} c\u{1EA5}p c\u{1EA7}n r\u{00E0} so\u{00E1}t.",
        ],
        'upcoming_70' => [
            'label' => "S\u{1EAF}p \u{0111}\u{1EE7} " . \App\Policies\InsurancePolicy::DEFAULT_AGE . " tu\u{1ED5}i",
            'age' => \App\Policies\InsurancePolicy::DEFAULT_AGE,
            'type' => 'upcoming',
            'purpose' => "S\u{1EAF}p \u{0111}\u{1EBF}n tu\u{1ED5}i chuy\u{1EC3}n b\u{1EA3}o hi\u{1EC3}m ng\u{01B0}\u{1EDD}i cao tu\u{1ED5}i",
            'exclude_if_field' => 'health_insurance_group',
            'exclude_if_values' => ["Ng\u{01B0}\u{1EDD}i cao tu\u{1ED5}i", "\u{0110}\u{1ED1}i t\u{01B0}\u{1EE3}ng kh\u{00E1}c"],
            'exclude_if_prefixes' => ["Ng\u{01B0}\u{1EDD}i cao tu\u{1ED5}i", "\u{0110}\u{1ED1}i t\u{01B0}\u{1EE3}ng kh\u{00E1}c"],
            'message' => "C\u{00F3} %d ng\u{01B0}\u{1EDD}i s\u{1EBD} \u{0111}\u{1EE7} " . \App\Policies\InsurancePolicy::DEFAULT_AGE . " tu\u{1ED5}i trong v\u{00F2}ng " . \App\Policies\AgePolicy::UPCOMING_POLICY_LOOKAHEAD_DAYS . " ng\u{00E0}y ch\u{01B0}a chuy\u{1EC3}n b\u{1EA3}o hi\u{1EC3}m ng\u{01B0}\u{1EDD}i cao tu\u{1ED5}i.",
        ],
        'upcoming_75' => [
            'label' => "S\u{1EAF}p \u{0111}\u{1EE7} " . \App\Config\CitizenPolicyDefaults::SOCIAL_ALLOWANCE_DEFAULT_AGE . " tu\u{1ED5}i",
            'age' => \App\Config\CitizenPolicyDefaults::SOCIAL_ALLOWANCE_DEFAULT_AGE,
            'type' => 'upcoming',
            'purpose' => "Sáº¯p Ä‘áº¿n tuá»•i rÃ  soÃ¡t trá»£ cáº¥p",
            'exclude_if_flag' => 'social_assistance',
            'message' => "C\u{00F3} %d ng\u{01B0}\u{1EDD}i ch\u{01B0}a h\u{01B0}\u{1EDF}ng tr\u{1EE3} c\u{1EA5}p s\u{1EBD} \u{0111}\u{1EE7} " . \App\Config\CitizenPolicyDefaults::SOCIAL_ALLOWANCE_DEFAULT_AGE . " tu\u{1ED5}i trong v\u{00F2}ng " . \App\Policies\AgePolicy::UPCOMING_POLICY_LOOKAHEAD_DAYS . " ng\u{00E0}y.",
        ],
    ],
];
