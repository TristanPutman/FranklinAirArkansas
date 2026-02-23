<?php
// Service pricing logic — single source of truth
// Prices in cents to avoid floating-point issues

function getPricingTable() {
    return [
        'load_calc' => [
            'label'        => 'Precision Load Calculation',
            'base_cents'   => 19000,   // $190
            'addl_cents'   => 13900,   // $139 per additional system
        ],
        'load_calc_duct' => [
            'label'        => 'Load Calc + Duct Design',
            'base_cents'   => 45000,   // $450
            'addl_cents'   => 33800,   // $338 per additional system
        ],
        'equipment_verification' => [
            'label'        => 'Equipment Verification',
            'base_cents'   => 9900,    // $99
            'addl_cents'   => 9900,    // $99 per system (flat rate)
        ],
        'rescheck' => [
            'label'        => 'REScheck Energy Calculation',
            'base_cents'   => 17000,   // $170
            'addl_cents'   => 0,
        ],
        'complete_package' => [
            'label'        => 'Complete Design Package',
            'base_cents'   => 54900,   // $549
            'addl_cents'   => 43700,   // $437 per additional system
        ],
        'commercial' => [
            'label'        => 'Commercial HVAC Reports',
            'base_cents'   => 0,       // quote required
            'addl_cents'   => 0,
        ],
    ];
}

function calculatePrice($serviceType, $numSystems = 1, $rush = false) {
    $pricing = getPricingTable();

    if (!isset($pricing[$serviceType])) {
        return null;
    }

    $service = $pricing[$serviceType];

    // Commercial is quote-based
    if ($serviceType === 'commercial') {
        return ['total_cents' => 0, 'breakdown' => 'Call for quote'];
    }

    $total = $service['base_cents'];
    if ($numSystems > 1) {
        $total += $service['addl_cents'] * ($numSystems - 1);
    }

    $rushFee = 0;
    if ($rush) {
        $rushFee = 7500; // $75
        $total += $rushFee;
    }

    return [
        'total_cents'  => $total,
        'base_cents'   => $service['base_cents'],
        'addl_cents'   => $service['addl_cents'],
        'addl_count'   => max(0, $numSystems - 1),
        'rush_cents'   => $rushFee,
        'service_label' => $service['label'],
    ];
}

function getServiceLabel($serviceType) {
    $pricing = getPricingTable();
    return $pricing[$serviceType]['label'] ?? $serviceType;
}
