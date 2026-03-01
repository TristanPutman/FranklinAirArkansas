<?php
// Service pricing logic — single source of truth
// Per-sqft pricing with minimums. Prices in cents to avoid floating-point issues.

function getPricingTable() {
    return [
        'manual_j' => [
            'label'          => 'Manual J Load Calculation',
            'per_sqft_cents' => 15,      // $0.15/sqft
            'min_cents'      => 35000,   // $350 minimum
        ],
        'manual_jd' => [
            'label'          => 'Manual J & D',
            'per_sqft_cents' => 35,      // $0.35/sqft
            'min_cents'      => 35000,   // $350 minimum
        ],
        'manual_jds' => [
            'label'          => 'Manual J, D, & S',
            'per_sqft_cents' => 50,      // $0.50/sqft
            'min_cents'      => 35000,   // $350 minimum
        ],
        'rescheck' => [
            'label'          => 'REScheck Energy Calculation',
            'per_sqft_cents' => 0,
            'min_cents'      => 17000,   // $170 flat
        ],
        'commercial' => [
            'label'          => 'Commercial HVAC Reports',
            'per_sqft_cents' => 0,
            'min_cents'      => 0,       // quote required
        ],
    ];
}

function calculatePrice($serviceType, $sqft = 0, $rush = false) {
    $pricing = getPricingTable();

    if (!isset($pricing[$serviceType])) {
        return null;
    }

    $service = $pricing[$serviceType];

    // Commercial is quote-based
    if ($serviceType === 'commercial') {
        return ['total_cents' => 0, 'breakdown' => 'Call for quote'];
    }

    // REScheck is flat-rate
    if ($serviceType === 'rescheck') {
        $total = $service['min_cents'];
        $rushFee = 0;
        if ($rush) {
            $rushFee = 7500;
            $total += $rushFee;
        }
        return [
            'total_cents'    => $total,
            'base_cents'     => $service['min_cents'],
            'per_sqft_cents' => 0,
            'sqft'           => 0,
            'rush_cents'     => $rushFee,
            'service_label'  => $service['label'],
        ];
    }

    // Per-sqft pricing with minimum
    $sqft = max(0, intval($sqft));
    $sqftTotal = $service['per_sqft_cents'] * $sqft;
    $base = max($sqftTotal, $service['min_cents']);

    $rushFee = 0;
    if ($rush) {
        $rushFee = 7500; // $75
        $base += $rushFee;
    }

    return [
        'total_cents'    => $base,
        'base_cents'     => max($sqftTotal, $service['min_cents']) - $rushFee + $rushFee, // net before rush
        'per_sqft_cents' => $service['per_sqft_cents'],
        'min_cents'      => $service['min_cents'],
        'sqft'           => $sqft,
        'sqft_total'     => $sqftTotal,
        'rush_cents'     => $rushFee,
        'service_label'  => $service['label'],
    ];
}

function getServiceLabel($serviceType) {
    $pricing = getPricingTable();
    return $pricing[$serviceType]['label'] ?? $serviceType;
}
