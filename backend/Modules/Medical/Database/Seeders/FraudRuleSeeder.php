<?php

namespace Modules\Medical\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fraud Rule Seeder
 *
 * Seeds default fraud detection rules.
 * These rules are configurable via admin interface.
 */
class FraudRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            // Frequency Rules
            [
                'rule_code' => 'FREQ_HIGH_CLAIMS_24H',
                'name' => 'High Claims in 24 Hours',
                'description' => 'Member has submitted more than threshold claims in the last 24 hours',
                'category' => 'frequency',
                'severity' => 'high',
                'points' => 30,
                'rule_logic' => ['threshold' => 5],
                'is_active' => true,
                'is_quick_check' => true,
                'priority' => 100,
                'applies_to' => ['claim', 'preauth'],
            ],
            [
                'rule_code' => 'FREQ_HIGH_CLAIMS_7D',
                'name' => 'High Claims in 7 Days',
                'description' => 'Member has submitted more than threshold claims in the last 7 days',
                'category' => 'frequency',
                'severity' => 'medium',
                'points' => 20,
                'rule_logic' => ['threshold' => 10],
                'is_active' => true,
                'is_quick_check' => false,
                'priority' => 90,
                'applies_to' => ['claim'],
            ],
            [
                'rule_code' => 'FREQ_HIGH_CLAIMS_30D',
                'name' => 'High Claims in 30 Days',
                'description' => 'Member has submitted more than threshold claims in the last 30 days',
                'category' => 'frequency',
                'severity' => 'medium',
                'points' => 15,
                'rule_logic' => ['threshold' => 20],
                'is_active' => true,
                'is_quick_check' => false,
                'priority' => 80,
                'applies_to' => ['claim'],
            ],

            // Amount Rules
            [
                'rule_code' => 'AMT_EXCEEDS_AVG_2X',
                'name' => 'Amount Exceeds 2x Average',
                'description' => 'Claim amount is more than 2 times the member average',
                'category' => 'amount',
                'severity' => 'low',
                'points' => 10,
                'rule_logic' => ['multiplier' => 2],
                'is_active' => true,
                'is_quick_check' => false,
                'priority' => 70,
                'applies_to' => ['claim'],
            ],
            [
                'rule_code' => 'AMT_EXCEEDS_AVG_3X',
                'name' => 'Amount Exceeds 3x Average',
                'description' => 'Claim amount is more than 3 times the member average',
                'category' => 'amount',
                'severity' => 'medium',
                'points' => 20,
                'rule_logic' => ['multiplier' => 3],
                'is_active' => true,
                'is_quick_check' => true,
                'priority' => 75,
                'applies_to' => ['claim', 'preauth'],
            ],
            [
                'rule_code' => 'AMT_HIGH_VALUE',
                'name' => 'High Value Claim',
                'description' => 'Claim amount exceeds high value threshold',
                'category' => 'amount',
                'severity' => 'medium',
                'points' => 15,
                'rule_logic' => ['threshold' => 50000],
                'is_active' => true,
                'is_quick_check' => true,
                'priority' => 85,
                'applies_to' => ['claim', 'preauth'],
            ],
            [
                'rule_code' => 'AMT_ROUND_NUMBER',
                'name' => 'Suspiciously Round Amount',
                'description' => 'Claim amount is an exact round number (e.g., 10000)',
                'category' => 'amount',
                'severity' => 'low',
                'points' => 5,
                'rule_logic' => [],
                'is_active' => true,
                'is_quick_check' => false,
                'priority' => 50,
                'applies_to' => ['claim'],
            ],

            // Pattern Rules
            [
                'rule_code' => 'PATTERN_RAPID_EXHAUSTION',
                'name' => 'Rapid Benefit Exhaustion',
                'description' => 'Member is rapidly exhausting multiple benefits',
                'category' => 'pattern',
                'severity' => 'high',
                'points' => 25,
                'rule_logic' => ['threshold' => 90, 'days' => 30],
                'is_active' => true,
                'is_quick_check' => false,
                'priority' => 95,
                'applies_to' => ['claim'],
            ],
            [
                'rule_code' => 'PATTERN_SAME_DIAGNOSIS',
                'name' => 'Repeated Same Diagnosis',
                'description' => 'Member has claimed same diagnosis multiple times in 90 days',
                'category' => 'pattern',
                'severity' => 'medium',
                'points' => 15,
                'rule_logic' => ['threshold' => 5, 'days' => 90],
                'is_active' => true,
                'is_quick_check' => false,
                'priority' => 70,
                'applies_to' => ['claim'],
            ],
            [
                'rule_code' => 'PATTERN_CONSECUTIVE_DAYS',
                'name' => 'Consecutive Day Claims',
                'description' => 'Member has claims on consecutive days',
                'category' => 'pattern',
                'severity' => 'low',
                'points' => 10,
                'rule_logic' => ['threshold' => 3],
                'is_active' => true,
                'is_quick_check' => false,
                'priority' => 60,
                'applies_to' => ['claim'],
            ],

            // Provider Rules
            [
                'rule_code' => 'PROVIDER_HIGH_REJECTION',
                'name' => 'Provider High Rejection Rate',
                'description' => 'Provider has high claim rejection rate',
                'category' => 'provider',
                'severity' => 'medium',
                'points' => 15,
                'rule_logic' => ['threshold' => 0.3, 'min_claims' => 10],
                'is_active' => true,
                'is_quick_check' => true,
                'priority' => 80,
                'applies_to' => ['claim'],
            ],
            [
                'rule_code' => 'PROVIDER_NEW_RELATIONSHIP',
                'name' => 'New Provider High Claim',
                'description' => 'First claim from provider is high value',
                'category' => 'provider',
                'severity' => 'medium',
                'points' => 15,
                'rule_logic' => ['amount_threshold' => 5000],
                'is_active' => true,
                'is_quick_check' => false,
                'priority' => 75,
                'applies_to' => ['claim'],
            ],

            // Member Rules
            [
                'rule_code' => 'MEMBER_NEW_HIGH_CLAIM',
                'name' => 'New Member High Claim',
                'description' => 'New member submitting high value claim',
                'category' => 'member',
                'severity' => 'medium',
                'points' => 20,
                'rule_logic' => ['max_days' => 60, 'amount_threshold' => 10000],
                'is_active' => true,
                'is_quick_check' => true,
                'priority' => 85,
                'applies_to' => ['claim', 'preauth'],
            ],
            [
                'rule_code' => 'MEMBER_HIGH_REJECTION_RATE',
                'name' => 'Member High Rejection Rate',
                'description' => 'Member has high claim rejection rate',
                'category' => 'member',
                'severity' => 'high',
                'points' => 20,
                'rule_logic' => ['threshold' => 0.3, 'min_claims' => 5],
                'is_active' => true,
                'is_quick_check' => false,
                'priority' => 85,
                'applies_to' => ['claim'],
            ],

            // Temporal Rules
            [
                'rule_code' => 'TEMPORAL_WEEKEND_HOSPITAL',
                'name' => 'Weekend Hospital Claim',
                'description' => 'In-patient claim on weekend (potential false date)',
                'category' => 'temporal',
                'severity' => 'low',
                'points' => 5,
                'rule_logic' => [],
                'is_active' => true,
                'is_quick_check' => false,
                'priority' => 40,
                'applies_to' => ['claim'],
            ],
            [
                'rule_code' => 'TEMPORAL_MONTH_END',
                'name' => 'Month End Claim',
                'description' => 'Claim submitted at end of month',
                'category' => 'temporal',
                'severity' => 'low',
                'points' => 3,
                'rule_logic' => ['day_threshold' => 28],
                'is_active' => true,
                'is_quick_check' => false,
                'priority' => 30,
                'applies_to' => ['claim'],
            ],

            // Line Item Rules
            [
                'rule_code' => 'LINES_MANY_SERVICES',
                'name' => 'Many Services on Single Claim',
                'description' => 'Claim has unusually many line items',
                'category' => 'pattern',
                'severity' => 'medium',
                'points' => 15,
                'rule_logic' => ['threshold' => 10],
                'is_active' => true,
                'is_quick_check' => false,
                'priority' => 65,
                'applies_to' => ['claim'],
            ],
            [
                'rule_code' => 'LINES_IDENTICAL_AMOUNTS',
                'name' => 'Identical Line Amounts',
                'description' => 'All claim lines have identical amounts (potential duplication)',
                'category' => 'pattern',
                'severity' => 'high',
                'points' => 25,
                'rule_logic' => ['min_lines' => 3],
                'is_active' => true,
                'is_quick_check' => false,
                'priority' => 90,
                'applies_to' => ['claim'],
            ],
        ];

        foreach ($rules as $rule) {
            $exists = DB::table('med_fraud_rules')
                ->where('rule_code', $rule['rule_code'])
                ->exists();

            if (!$exists) {
                DB::table('med_fraud_rules')->insert(array_merge($rule, [
                    'id' => Str::uuid(),
                    'rule_logic' => json_encode($rule['rule_logic']),
                    'applies_to' => json_encode($rule['applies_to']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }

        $this->command->info('Fraud rules seeded successfully: ' . count($rules) . ' rules');
    }
}
