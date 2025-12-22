<?php

namespace Modules\Medical\Constants;

final class MedicalDictionary
{
    // Gender
    public const GENDER_MALE = 'M';
    public const GENDER_FEMALE = 'F';
    public const GENDERS = [self::GENDER_MALE, self::GENDER_FEMALE];

    // Member Types
    public const MEMBER_PRINCIPAL = 'Principal';
    public const MEMBER_SPOUSE = 'Spouse';
    public const MEMBER_CHILD = 'Child';
    public const MEMBER_TYPES = [self::MEMBER_PRINCIPAL, self::MEMBER_SPOUSE, self::MEMBER_CHILD];

    // Plan Types
    public const PLAN_INDIVIDUAL = 'Individual';
    public const PLAN_CORPORATE = 'Corporate';
    public const PLAN_SME = 'SME';
}