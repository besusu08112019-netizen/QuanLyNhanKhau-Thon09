$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$sampleDir = Join-Path $root 'sample-data'
$tempRoot = Join-Path $root '.tmp-import-templates'

$householdHeaders = @(
  'Ma ho',
  'Ten chu ho',
  'Dia chi',
  'So dien thoai',
  'Ma dia ban',
  'Dien ho',
  'Ho ngheo',
  'Ho can ngheo',
  'Ghi chu'
)

$personHeaders = @(
  'Ma ho',
  'Ma nhan khau',
  'Ho va ten',
  'Gioi tinh',
  'Ngay sinh',
  'CCCD',
  'Ngay cap CCCD',
  'Noi cap CCCD',
  'So dien thoai',
  'Quan he voi chu ho',
  'Ho ten bo',
  'Ho ten me',
  'Dan toc',
  'Ton giao',
  'Nghe nghiep',
  'Hoc van',
  'Hon nhan',
  'Thuong tru',
  'Hien tai',
  'Trang thai',
  'Dia chi hien tai',
  'Dang vien',
  'Doan vien Thanh nien',
  'Hoi vien Hoi Phu nu',
  'Hoi vien Hoi Nong dan',
  'Hoi vien Cuu chien binh',
  'Hoi vien Nguoi cao tuoi',
  'Nguoi co cong',
  'Than nhan liet si',
  'Thuong binh',
  'Benh binh',
  'Nhiem chat doc hoa hoc',
  'Nguoi bi dich bat tu day',
  'Thanh nien xung phong',
  'Anh hung khang chien',
  'Nguoi hoat dong cach mang',
  'Nguoi khuyet tat',
  'Bao tro xa hoi',
  'Co viec lam',
  'That nghiep',
  'Lao dong tu do',
  'Lao dong ngoai tinh',
  'Lao dong nuoc ngoai',
  'Chua di hoc',
  'Hoc sinh',
  'Sinh vien',
  'Nghi huu',
  'BHYT',
  'Ma so BHYT',
  'Nhom BHYT',
  'Ngay bat dau BHYT',
  'Ngay het han BHYT',
  'Noi dang ky kham chua benh'
)

$guideRows = @(
  @('Noi dung', 'Huong dan'),
  @('Dong du lieu', 'Nhap du lieu tu dong 2 cua sheet HoDan hoac NhanKhau. Khong doi ten sheet du lieu.'),
  @('Cot bat buoc ho dan', 'Ma ho, Dia chi. Ten chu ho nen nhap de ho so de tra cuu.'),
  @('Cot bat buoc nhan khau', 'Ma ho, Ho va ten, Ngay sinh. Ma ho phai ton tai truoc khi import nhan khau.'),
  @('Ngay thang', 'Dung dd/MM/yyyy hoac yyyy-MM-dd. CCCD, so dien thoai, ma ho, ma BHYT de dang Text.'),
  @('Cot Co/Khong', 'Nhap Co, Khong, 1, 0, X. Neu bo trong thi hieu la Khong, rieng BHYT bo trong mac dinh la Co.'),
  @('Ho co cong/khuyet tat', 'Khong nhap o mau ho dan. He thong tu suy ra tu cac cot chinh sach cua nhan khau trong ho.')
)

$catalogRows = @(
  @('Nhom', 'Gia tri goi y'),
  @('Gioi tinh', 'Nam; Nu; Khac'),
  @('Quan he voi chu ho', 'Chu ho; Vo; Chong; Con; Cha; Me; Khac'),
  @('Dien ho', 'Ho ngheo; Ho can ngheo; Ho moi thoat ngheo; Ho chinh sach; Ho co cong; Ho binh thuong; Khac'),
  @('Cu tru', 'Thuong tru; Tam tru'),
  @('Hien tai', 'O nha; Di vang; Tam vang'),
  @('Trang thai', 'Con song; Da chet'),
  @('Nhom BHYT', 'Ho gia dinh; Nguoi ngheo; Can ngheo; Tre em duoi 6 tuoi; Hoc sinh - Sinh vien; Nguoi lao dong; Nguoi huong luong huu; Nguoi co cong; Nguoi cao tuoi; Khac')
)

function ConvertTo-CsvLine([string[]] $Values) {
  return ($Values | ForEach-Object {
    $value = ''
    if ($null -ne $_) { $value = [string]$_ }
    '"' + ($value -replace '"', '""') + '"'
  }) -join ','
}

function Write-Utf8BomCsv([string] $Path, [string[]] $Headers) {
  $content = (ConvertTo-CsvLine $Headers) + "`r`n"
  $encoding = New-Object System.Text.UTF8Encoding $true
  [System.IO.File]::WriteAllText($Path, $content, $encoding)
}

function XmlEscape([string] $Value) {
  if ($null -eq $Value) { return '' }
  return [System.Security.SecurityElement]::Escape($Value)
}

function ColumnName([int] $Index) {
  $name = ''
  $number = $Index + 1
  while ($number -gt 0) {
    $rem = ($number - 1) % 26
    $name = [char]([int](65 + $rem)) + $name
    $number = [math]::Floor(($number - 1) / 26)
  }
  return $name
}

function New-SheetXml([object[]] $Rows, [int[]] $Widths) {
  $sb = New-Object System.Text.StringBuilder
  [void]$sb.AppendLine('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>')
  [void]$sb.AppendLine('<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">')
  if ($Widths.Count -gt 0) {
    [void]$sb.AppendLine('<cols>')
    for ($i = 0; $i -lt $Widths.Count; $i++) {
      $col = $i + 1
      [void]$sb.AppendLine(('<col min="{0}" max="{0}" width="{1}" customWidth="1"/>' -f $col, $Widths[$i]))
    }
    [void]$sb.AppendLine('</cols>')
  }
  [void]$sb.AppendLine('<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>')
  [void]$sb.AppendLine('<sheetData>')
  for ($r = 0; $r -lt $Rows.Count; $r++) {
    $rowNumber = $r + 1
    $height = if ($r -eq 0) { ' ht="24" customHeight="1"' } else { '' }
    [void]$sb.AppendLine(('<row r="{0}"{1}>' -f $rowNumber, $height))
    $cells = $Rows[$r]
    for ($c = 0; $c -lt $cells.Count; $c++) {
      $ref = (ColumnName $c) + $rowNumber
      $style = if ($r -eq 0) { ' s="1"' } else { ' s="2"' }
      [void]$sb.AppendLine(('<c r="{0}" t="inlineStr"{1}><is><t>{2}</t></is></c>' -f $ref, $style, (XmlEscape $cells[$c])))
    }
    [void]$sb.AppendLine('</row>')
  }
  [void]$sb.AppendLine('</sheetData>')
  [void]$sb.AppendLine('</worksheet>')
  return $sb.ToString()
}

function Write-Xlsx([string] $Path, [string] $DataSheetName, [string[]] $Headers) {
  $workDir = Join-Path $tempRoot ([System.IO.Path]::GetFileNameWithoutExtension($Path))
  if (Test-Path $workDir) {
    $resolved = [System.IO.Path]::GetFullPath($workDir)
    $allowed = [System.IO.Path]::GetFullPath($tempRoot)
    if (-not $resolved.StartsWith($allowed, [System.StringComparison]::OrdinalIgnoreCase)) {
      throw "Refusing to remove temp path outside workspace: $resolved"
    }
    Remove-Item -LiteralPath $workDir -Recurse -Force
  }
  New-Item -ItemType Directory -Force -Path $workDir | Out-Null
  New-Item -ItemType Directory -Force -Path (Join-Path $workDir '_rels') | Out-Null
  New-Item -ItemType Directory -Force -Path (Join-Path $workDir 'xl') | Out-Null
  New-Item -ItemType Directory -Force -Path (Join-Path $workDir 'xl\_rels') | Out-Null
  New-Item -ItemType Directory -Force -Path (Join-Path $workDir 'xl\worksheets') | Out-Null

  @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>
'@ | Set-Content -Encoding UTF8 -LiteralPath (Join-Path $workDir '[Content_Types].xml')

  @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
'@ | Set-Content -Encoding UTF8 -LiteralPath (Join-Path $workDir '_rels\.rels')

  @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="$DataSheetName" sheetId="1" r:id="rId1"/>
    <sheet name="HuongDan" sheetId="2" r:id="rId2"/>
    <sheet name="DanhMuc" sheetId="3" r:id="rId3"/>
  </sheets>
</workbook>
"@ | Set-Content -Encoding UTF8 -LiteralPath (Join-Path $workDir 'xl\workbook.xml')

  @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>
  <Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
'@ | Set-Content -Encoding UTF8 -LiteralPath (Join-Path $workDir 'xl\_rels\workbook.xml.rels')

  @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts>
  <fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0F766E"/><bgColor indexed="64"/></patternFill></fill></fills>
  <borders count="2"><border/><border><left style="thin"><color rgb="FFD9E2EC"/></left><right style="thin"><color rgb="FFD9E2EC"/></right><top style="thin"><color rgb="FFD9E2EC"/></top><bottom style="thin"><color rgb="FFD9E2EC"/></bottom></border></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="49" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="49" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyNumberFormat="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs>
  <cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
</styleSheet>
'@ | Set-Content -Encoding UTF8 -LiteralPath (Join-Path $workDir 'xl\styles.xml')

  $dataWidths = @()
  foreach ($h in $Headers) { $dataWidths += [Math]::Min([Math]::Max($h.Length + 4, 14), 28) }
  New-SheetXml -Rows (,$Headers) -Widths $dataWidths | Set-Content -Encoding UTF8 -LiteralPath (Join-Path $workDir 'xl\worksheets\sheet1.xml')
  New-SheetXml -Rows $guideRows -Widths @(28, 96) | Set-Content -Encoding UTF8 -LiteralPath (Join-Path $workDir 'xl\worksheets\sheet2.xml')
  New-SheetXml -Rows $catalogRows -Widths @(28, 120) | Set-Content -Encoding UTF8 -LiteralPath (Join-Path $workDir 'xl\worksheets\sheet3.xml')

  if (Test-Path $Path) { Remove-Item -LiteralPath $Path -Force }
  Add-Type -AssemblyName System.IO.Compression
  Add-Type -AssemblyName System.IO.Compression.FileSystem
  $zip = [System.IO.Compression.ZipFile]::Open($Path, [System.IO.Compression.ZipArchiveMode]::Create)
  try {
    $basePath = [System.IO.Path]::GetFullPath($workDir)
    foreach ($file in Get-ChildItem -LiteralPath $workDir -Recurse -File) {
      $fullPath = [System.IO.Path]::GetFullPath($file.FullName)
      $entryName = $fullPath.Substring($basePath.Length).TrimStart('\', '/') -replace '\\', '/'
      [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $fullPath, $entryName) | Out-Null
    }
  } finally {
    $zip.Dispose()
  }
}

function Write-HtmlXls([string] $Path, [string[]] $Headers) {
  $headerHtml = ($Headers | ForEach-Object { '<th>' + (XmlEscape $_) + '</th>' }) -join ''
  $html = '<html><head><meta charset="utf-8"></head><body><table border="1"><tr>' + $headerHtml + '</tr></table></body></html>'
  $encoding = New-Object System.Text.UTF8Encoding $true
  [System.IO.File]::WriteAllText($Path, $html, $encoding)
}

function Write-XmlSpreadsheet([string] $Path) {
  $sb = New-Object System.Text.StringBuilder
  [void]$sb.AppendLine('<?xml version="1.0"?>')
  [void]$sb.AppendLine('<?mso-application progid="Excel.Sheet"?>')
  [void]$sb.AppendLine('<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">')
  [void]$sb.AppendLine('  <Styles><Style ss:ID="Header"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#0F766E" ss:Pattern="Solid"/></Style><Style ss:ID="Text"><NumberFormat ss:Format="@"/></Style></Styles>')
  foreach ($sheet in @(
    @{ Name = 'HoDan'; Headers = $householdHeaders },
    @{ Name = 'NhanKhau'; Headers = $personHeaders }
  )) {
    [void]$sb.AppendLine(('  <Worksheet ss:Name="{0}"><Table>' -f $sheet.Name))
    [void]$sb.AppendLine('    <Row>')
    foreach ($header in $sheet.Headers) {
      [void]$sb.AppendLine(('      <Cell ss:StyleID="Header"><Data ss:Type="String">{0}</Data></Cell>' -f (XmlEscape $header)))
    }
    [void]$sb.AppendLine('    </Row>')
    [void]$sb.AppendLine('  </Table></Worksheet>')
  }
  [void]$sb.AppendLine('</Workbook>')
  $encoding = New-Object System.Text.UTF8Encoding $true
  [System.IO.File]::WriteAllText($Path, $sb.ToString(), $encoding)
}

New-Item -ItemType Directory -Force -Path $sampleDir | Out-Null
New-Item -ItemType Directory -Force -Path $tempRoot | Out-Null

Write-Utf8BomCsv (Join-Path $sampleDir 'import_household_template.csv') $householdHeaders
Write-Utf8BomCsv (Join-Path $sampleDir 'import_person_template.csv') $personHeaders
Write-Utf8BomCsv (Join-Path $sampleDir 'import-template.csv') $personHeaders

Write-Xlsx (Join-Path $sampleDir 'Mau_Import_HoDan.xlsx') 'HoDan' $householdHeaders
Write-Xlsx (Join-Path $sampleDir 'Mau_Import_NhanKhau.xlsx') 'NhanKhau' $personHeaders

Write-HtmlXls (Join-Path $sampleDir 'Mau_Import_HoDan_ChinhQuyenSo.xls') $householdHeaders
Write-HtmlXls (Join-Path $sampleDir 'Mau_Import_NhanKhau_ChinhQuyenSo.xls') $personHeaders
Write-XmlSpreadsheet (Join-Path $sampleDir 'import_template_tenant.xls')

if (Test-Path $tempRoot) {
  $resolvedTemp = [System.IO.Path]::GetFullPath($tempRoot)
  $resolvedRoot = [System.IO.Path]::GetFullPath($root)
  if (-not $resolvedTemp.StartsWith($resolvedRoot, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw "Refusing to remove temp path outside workspace: $resolvedTemp"
  }
  Remove-Item -LiteralPath $tempRoot -Recurse -Force
}

Write-Host 'Import templates regenerated.'
