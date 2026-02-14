<?php

namespace App\Services\Pbac;

use Illuminate\Database\Eloquent\Model;

/**
 * PBAC Policy Evaluator
 *
 * Responsibility:
 *  - Takes pre-loaded & normalized policies
 *  - Evaluates them against subject, resource, and environment context
 *  - Returns a final allow / deny decision
 *
 * Important design rules:
 *  - Policies are evaluated by DESC priority
 *  - First matching policy decides (short-circuit)
 *  - DENY is the default fallback
 *
 * This class is intentionally:
 *  - Stateless
 *  - Pure (no DB calls)
 *  - Deterministic (same input → same output)
 */
class PbacEvaluator
{
    /**
     * Evaluate a permission decision
     *
     * @param array       $policies  Normalized PBAC policies
     * @param mixed       $subject   Authenticated subject (usually User)
     * @param Model|null  $resource  Target resource (optional)
     * @param array       $env       Environment context (ip, time, headers, etc)
     *
     * @return bool  TRUE = allowed, FALSE = denied
     */
    public function evaluate(
        array $policies,
        $subject,
        ?Model $resource,
        array $env
    ): bool {
        /**
         * PBAC Rule:
         * Highest priority policies must always be evaluated first.
         * We do NOT rely on DB order here to keep evaluator independent.
         */
        usort($policies, fn($a, $b) => $b['priority'] <=> $a['priority']);

        /**
         * First matching policy wins.
         * This allows explicit DENY rules to override ALLOW rules.
         */
        foreach($policies as $policy) {
            if ($this->conditionMatch($policy['conditions'], $subject, $resource, $env)) {
                return $policy['effect'] === 'allow';
            }
        }

        /**
         * Default security posture:
         * If no policy matches → DENY
         */
        return false;
    }
    
    /**
     * Check whether ALL conditions of a policy match
     *
     * Condition logic:
     *  - AND semantics (every condition must pass)
     *  - Empty condition set = unconditional policy
     * 
     * @param array       $policies  Normalized PBAC policies
     * @param mixed       $subject   Authenticated subject (usually User)
     * @param Model|null  $resource  Target resource (optional)
     * @param array       $env       Environment context (ip, time, headers, etc)
     *
     * @return bool  TRUE = allowed, FALSE = denied
     * 
     */
    protected function conditionMatch(
        array $conditions,
        $subject,
        ?Model $resource,
        array $env
    ): bool {
        /**
         * Unconditional policy
         * (common for tenant-wide or role-wide permissions)
         */
        if (empty($conditions)) {
            return true;
        }

        foreach($conditions as $condition) {
            if (!$this->evaluateCondition($condition, $subject, $resource, $env)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single PBAC condition
     *
     * A condition consists of:
     *  - left operand  (always a reference)
     *  - operator      (=, !=, >, <, in, etc)
     *  - right operand (value OR reference)
     */
    protected function evaluateCondition(
        array $condition,
        $subject,
        ?Model $resource,
        array $env
    ): bool {
        /**
         * Resolve LEFT operand
         * Example: subject.id, resource.owner_id, env.ip
         */
        $left = $this->resolveOperand(
            $condition['left'],
            $subject,
            $resource,
            $env
        );

        /**
         * Resolve RIGHT operand
         *
         * right_type:
         *  - value → literal value from DB
         *  - ref   → resolved dynamically from subject/resource/env
         */
        $right = $this->resolveOperand(
            $condition['right_type'] === 'ref'
                ? $condition['right_ref']
                : $condition['right'],
            $subject,
            $resource,
            $env
        );

        /**
         * Operator evaluation
         *
         * IMPORTANT:
         *  - Loose equality used intentionally (=) for flexibility
         *  - "in" is strict to avoid type confusion
         */
        return match($condition['operator']) {
            '=' => $left == $right,
            '!=' => $left != $right,
            '>' => $left > $right,
            '<' => $left < $right,
            'in' => is_array($right) && in_array($left, $right, true),
            default => false,   // Unknown operators fail safely
        };
    }

    /**
     * Resolve an operand path into a concrete value
     *
     * Supported namespaces:
     *  - subject.*   → authenticated entity (User)
     *  - resource.*  → target model (optional)
     *  - env.*       → request/environment context
     *
     * Examples:
     *  - subject.id
     *  - resource.owner_id
     *  - env.ip
     *  - env.time.hour
     */
    protected function resolveOperand(
        string $path,
        $subject,
        ?Model $resource,
        array $env
    ) {
        return match(true) {
            /**
             * Subject-based attributes
             */
            str_starts_with($path, 'subject.') => 
                data_get($subject, substr($path,8)),

            /**
             * Resource-based attributes
             * If resource is NULL, evaluation safty degrades
             */
            str_starts_with($path, 'resource.') =>
                $resource ? data_get($resource, substr($path, 9)) : null,

            /**
             * Environment attributes
             * (request, headers, ip, time, tenant context)
             */
            str_starts_with($path, 'env.') => 
                data_get($env, substr($path, 4)),
            
            /**
             * Literal fallback
             * Used when right_type = value
             */
            default => $path,
        };
    }
}
