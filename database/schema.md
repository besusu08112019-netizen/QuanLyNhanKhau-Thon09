# Database Schema

Google Sheets database gom cac sheet sau.

## households

`id`, `householdCode`, `headCitizenId`, `address`, `phone`, `areaCode`, `memberCount`, `note`, `status`, `createdAt`, `createdBy`, `updatedAt`, `updatedBy`, `deletedAt`, `deletedBy`, `headCitizenName`, `poorHousehold`, `nearPoorHousehold`

`meritoriousPolicy` và `disabledPolicy` là dữ liệu suy ra từ nhân khẩu, không lưu trong bảng `households`.

## citizens

`id`, `citizenCode`, `householdId`, `fullName`, `gender`, `dateOfBirth`, `identityNumber`, `identityIssueDate`, `identityIssuePlace`, `relationship`, `fatherName`, `motherName`, `ethnicity`, `religion`, `occupation`, `phone`, `permanentAddress`, `currentAddress`, `educationLevel`, `maritalStatus`, `status`, `createdAt`, `createdBy`, `updatedAt`, `updatedBy`, `deletedAt`, `deletedBy`, `presenceStatus`

## movements

`id`, `citizenId`, `householdId`, `type`, `fromAddress`, `toAddress`, `reason`, `effectiveDate`, `documentNumber`, `note`, `status`, `createdAt`, `createdBy`, `updatedAt`, `updatedBy`, `deletedAt`, `deletedBy`

## party_members

`id`, `villageId`, `citizenId`, `partyMemberCode`, `partyCardNumber`, `joinedPartyDate`, `officialPartyDate`, `branchName`, `parentPartyOrg`, `partyPosition`, `governmentPosition`, `educationLevel`, `professionalLevel`, `politicalTheoryLevel`, `memberType`, `activityStatus`, `note`, `status`, `createdAt`, `createdBy`, `updatedAt`, `updatedBy`, `deletedAt`, `deletedBy`

Moi ban ghi lien ket 1-1 voi mot `citizens.id`. Ho ten, ngay sinh, gioi tinh, CCCD, dien thoai va dia chi duoc doc truc tiep tu bang `citizens`, khong nhap tay trong bang nay.

## users

`id`, `email`, `displayName`, `role`, `status`, `lastLoginAt`, `createdAt`, `createdBy`, `updatedAt`, `updatedBy`, `deletedAt`, `deletedBy`

## permissions

`id`, `role`, `module`, `action`, `allowed`, `createdAt`, `createdBy`, `updatedAt`, `updatedBy`

## logs

`id`, `timestamp`, `actorEmail`, `action`, `module`, `entityId`, `level`, `message`, `metadata`

## backups

`id`, `timestamp`, `fileId`, `fileName`, `spreadsheetId`, `createdBy`, `status`, `note`

## settings

`key`, `value`, `updatedAt`, `updatedBy`
