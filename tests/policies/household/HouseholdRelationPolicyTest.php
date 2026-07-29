<?php

use App\Policies\HouseholdRelationPolicy;

policy_test('HouseholdRelationPolicy standard relationships are available', function (): void {
    $relationships = HouseholdRelationPolicy::standardRelationships();

    foreach ([
        HouseholdRelationPolicy::HEAD,
        HouseholdRelationPolicy::WIFE,
        HouseholdRelationPolicy::HUSBAND,
        HouseholdRelationPolicy::SON,
        HouseholdRelationPolicy::DAUGHTER,
        HouseholdRelationPolicy::DAUGHTER_IN_LAW,
        HouseholdRelationPolicy::SON_IN_LAW,
        HouseholdRelationPolicy::GRANDCHILD,
        HouseholdRelationPolicy::PATERNAL_GRANDCHILD,
        HouseholdRelationPolicy::MATERNAL_GRANDCHILD,
        HouseholdRelationPolicy::FATHER,
        HouseholdRelationPolicy::MOTHER,
        HouseholdRelationPolicy::GRANDFATHER,
        HouseholdRelationPolicy::GRANDMOTHER,
        HouseholdRelationPolicy::OLDER_BROTHER,
        HouseholdRelationPolicy::OLDER_SISTER,
        HouseholdRelationPolicy::YOUNGER_SIBLING,
        HouseholdRelationPolicy::OTHER_RELATIVE,
    ] as $relationship) {
        policy_assert_true(in_array($relationship, $relationships, true), 'Missing standard relationship: ' . $relationship);
    }
});

policy_test('HouseholdRelationPolicy normalizes relationship labels', function (): void {
    policy_assert_same(HouseholdRelationPolicy::HEAD, HouseholdRelationPolicy::normalizeRelationship('chu ho'), 'Head alias must normalize.');
    policy_assert_same(HouseholdRelationPolicy::WIFE, HouseholdRelationPolicy::normalizeRelationship('Vợ'), 'Wife label must normalize.');
    policy_assert_same(HouseholdRelationPolicy::SON, HouseholdRelationPolicy::normalizeRelationship('con', 'Nam'), 'Generic child with male gender must normalize to son.');
    policy_assert_same(HouseholdRelationPolicy::DAUGHTER, HouseholdRelationPolicy::normalizeRelationship('con', 'Nữ'), 'Generic child with female gender must normalize to daughter.');
    policy_assert_same(HouseholdRelationPolicy::OTHER_RELATIVE, HouseholdRelationPolicy::normalizeRelationship(''), 'Empty relationship must normalize to other relative.');
    policy_assert_same(HouseholdRelationPolicy::UNKNOWN, HouseholdRelationPolicy::normalizeRelationship('Chưa xác định'), 'Unknown relationship must remain unknown.');
});

policy_test('HouseholdRelationPolicy infers relationships within the same household', function (): void {
    $members = [
        ['id' => 1, 'full_name' => 'Nguyễn Văn An', 'gender' => 'Nam', 'father_name' => '', 'mother_name' => '', 'relationship' => ''],
        ['id' => 2, 'full_name' => 'Nguyễn Văn Bình', 'gender' => 'Nam', 'father_name' => 'Nguyễn Văn An', 'mother_name' => '', 'relationship' => ''],
        ['id' => 3, 'full_name' => 'Nguyễn Thị Chi', 'gender' => 'Nữ', 'father_name' => 'Nguyễn Văn An', 'mother_name' => '', 'relationship' => ''],
        ['id' => 4, 'full_name' => 'Nguyễn Văn Dũng', 'gender' => 'Nam', 'father_name' => 'Nguyễn Văn Bình', 'mother_name' => '', 'relationship' => ''],
    ];

    $relations = HouseholdRelationPolicy::inferHouseholdRelationships($members, 'Nguyễn Văn An');

    policy_assert_same(HouseholdRelationPolicy::HEAD, $relations[1] ?? null, 'Head must be inferred by household head name.');
    policy_assert_same(HouseholdRelationPolicy::SON, $relations[2] ?? null, 'Male child of head must be inferred as son.');
    policy_assert_same(HouseholdRelationPolicy::DAUGHTER, $relations[3] ?? null, 'Female child of head must be inferred as daughter.');
    policy_assert_same(HouseholdRelationPolicy::GRANDCHILD, $relations[4] ?? null, 'Child of a child must be inferred as grandchild.');
});

policy_test('HouseholdRelationPolicy does not overwrite locked relationships', function (): void {
    $members = [
        ['id' => 1, 'full_name' => 'Nguyễn Văn An', 'gender' => 'Nam', 'father_name' => '', 'mother_name' => '', 'relationship' => ''],
        ['id' => 2, 'full_name' => 'Trần Thị Bình', 'gender' => 'Nữ', 'father_name' => '', 'mother_name' => '', 'relationship' => 'Vợ'],
        ['id' => 3, 'full_name' => 'Nguyễn Văn Chi', 'gender' => 'Nam', 'father_name' => 'Nguyễn Văn An', 'mother_name' => 'Trần Thị Bình', 'relationship' => ''],
    ];

    $relations = HouseholdRelationPolicy::inferHouseholdRelationships($members, 'Nguyễn Văn An');

    policy_assert_same(HouseholdRelationPolicy::WIFE, $relations[2] ?? null, 'Existing wife relationship must stay locked.');
    policy_assert_same(HouseholdRelationPolicy::SON, $relations[3] ?? null, 'Unlocked child must still be inferred.');
});

policy_test('HouseholdRelationPolicy avoids ambiguous household head inference', function (): void {
    $members = [
        ['id' => 1, 'full_name' => 'Nguyễn Văn An', 'gender' => 'Nam', 'father_name' => '', 'mother_name' => '', 'relationship' => ''],
        ['id' => 2, 'full_name' => 'Nguyễn Văn An', 'gender' => 'Nam', 'father_name' => '', 'mother_name' => '', 'relationship' => ''],
    ];

    policy_assert_same([], HouseholdRelationPolicy::inferHouseholdRelationships($members, 'Nguyễn Văn An'), 'Ambiguous head name must not infer relationships.');
});

policy_test('HouseholdRelationPolicy keeps legacy great-grandchild inference compatible', function (): void {
    $members = [
        ['id' => 1, 'full_name' => 'Nguyễn Văn An', 'gender' => 'Nam', 'father_name' => '', 'mother_name' => '', 'relationship' => ''],
        ['id' => 2, 'full_name' => 'Nguyễn Văn Bình', 'gender' => 'Nam', 'father_name' => 'Nguyễn Văn An', 'mother_name' => '', 'relationship' => ''],
        ['id' => 3, 'full_name' => 'Nguyễn Văn Chi', 'gender' => 'Nam', 'father_name' => 'Nguyễn Văn Bình', 'mother_name' => '', 'relationship' => ''],
        ['id' => 4, 'full_name' => 'Nguyễn Văn Dũng', 'gender' => 'Nam', 'father_name' => 'Nguyễn Văn Chi', 'mother_name' => '', 'relationship' => ''],
    ];

    $relations = HouseholdRelationPolicy::inferHouseholdRelationships($members, 'Nguyễn Văn An');

    policy_assert_same(HouseholdRelationPolicy::GREAT_GRANDCHILD, $relations[4] ?? null, 'Existing great-grandchild inference must stay compatible.');
});
