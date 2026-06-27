# Database Schema

Google Sheets database gom cac sheet sau.

## households

`id`, `householdCode`, `headCitizenId`, `address`, `hamlet`, `phone`, `areaCode`, `memberCount`, `note`, `status`, `createdAt`, `createdBy`, `updatedAt`, `updatedBy`, `deletedAt`, `deletedBy`

## citizens

`id`, `citizenCode`, `householdId`, `fullName`, `gender`, `dateOfBirth`, `identityNumber`, `identityIssueDate`, `identityIssuePlace`, `relationship`, `ethnicity`, `religion`, `occupation`, `phone`, `permanentAddress`, `currentAddress`, `educationLevel`, `maritalStatus`, `status`, `createdAt`, `createdBy`, `updatedAt`, `updatedBy`, `deletedAt`, `deletedBy`

## movements

`id`, `citizenId`, `householdId`, `type`, `fromAddress`, `toAddress`, `reason`, `effectiveDate`, `documentNumber`, `note`, `status`, `createdAt`, `createdBy`, `updatedAt`, `updatedBy`, `deletedAt`, `deletedBy`

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
