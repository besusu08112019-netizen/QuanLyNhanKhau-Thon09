var Domain = Domain || {};

Domain.App = Object.freeze({
  NAME: 'He thong Quan ly Nhan khau Thon 09',
  VERSION: '1.0.0',
  TIMEZONE: 'Asia/Ho_Chi_Minh',
  SPREADSHEET_ID_PROPERTY: 'DATABASE_SPREADSHEET_ID',
  BACKUP_FOLDER_ID_PROPERTY: 'BACKUP_FOLDER_ID',
  PDF_FOLDER_ID_PROPERTY: 'PDF_FOLDER_ID'
});

Domain.Status = Object.freeze({
  ACTIVE: 'ACTIVE',
  INACTIVE: 'INACTIVE',
  DELETED: 'DELETED'
});

Domain.Roles = Object.freeze({
  SUPER_ADMIN: 'SUPER_ADMIN',
  ADMIN: 'ADMIN',
  OFFICER: 'OFFICER',
  VIEWER: 'VIEWER'
});

Domain.Modules = Object.freeze({
  DASHBOARD: 'dashboard',
  HOUSEHOLD: 'household',
  CITIZEN: 'citizen',
  MOVEMENT: 'movement',
  REPORT: 'report',
  PDF: 'pdf',
  BACKUP: 'backup',
  PERMISSION: 'permission',
  USER: 'user',
  LOGS: 'logs',
  SETTINGS: 'settings',
  IMPORT: 'import'
});

Domain.Actions = Object.freeze({
  READ: 'read',
  CREATE: 'create',
  UPDATE: 'update',
  DELETE: 'delete',
  EXPORT: 'export',
  ADMIN: 'admin'
});

Domain.Tables = Object.freeze({
  HOUSEHOLDS: 'households',
  CITIZENS: 'citizens',
  MOVEMENTS: 'movements',
  USERS: 'users',
  PERMISSIONS: 'permissions',
  LOGS: 'logs',
  BACKUPS: 'backups',
  SETTINGS: 'settings'
});

Domain.Schema = Object.freeze({
  households: ['id','householdCode','headCitizenId','address','phone','areaCode','memberCount','note','status','createdAt','createdBy','updatedAt','updatedBy','deletedAt','deletedBy','headCitizenName','meritoriousFamily','poorHousehold','nearPoorHousehold','disabledHousehold'],
  citizens: ['id','citizenCode','householdId','fullName','gender','dateOfBirth','identityNumber','identityIssueDate','identityIssuePlace','relationship','ethnicity','religion','occupation','phone','permanentAddress','currentAddress','educationLevel','maritalStatus','status','createdAt','createdBy','updatedAt','updatedBy','deletedAt','deletedBy'],
  movements: ['id','citizenId','householdId','type','fromAddress','toAddress','reason','effectiveDate','documentNumber','note','status','createdAt','createdBy','updatedAt','updatedBy','deletedAt','deletedBy'],
  users: ['id','email','displayName','role','status','lastLoginAt','createdAt','createdBy','updatedAt','updatedBy','deletedAt','deletedBy'],
  permissions: ['id','role','module','action','allowed','createdAt','createdBy','updatedAt','updatedBy'],
  logs: ['id','timestamp','actorEmail','action','module','entityId','level','message','metadata'],
  backups: ['id','timestamp','fileId','fileName','spreadsheetId','createdBy','status','note'],
  settings: ['key','value','updatedAt','updatedBy']
});

Domain.RequiredFields = Object.freeze({
  households: ['householdCode','address'],
  citizens: ['citizenCode','householdId','fullName','gender','dateOfBirth','relationship'],
  movements: ['citizenId','type','effectiveDate'],
  users: ['email','displayName','role'],
  permissions: ['role','module','action']
});