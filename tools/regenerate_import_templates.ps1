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

$householdSampleRows = @(
  @('HD001', 'Nguyễn Văn An', 'Thôn 9, Xã Minh Châu', '0912345678', 'THON09', 'Ho binh thuong', 'Khong', 'Khong', 'Hộ mẫu có nhiều thế hệ'),
  @('HD002', 'Trần Thị Bình', 'Thôn 9, Xã Minh Châu', '0987654321', 'THON09', 'Ho can ngheo', 'Khong', 'Co', 'Hộ mẫu cận nghèo'),
  @('HD003', 'Lê Văn Cường', 'Thôn 9, Xã Minh Châu', '0901122334', 'THON09', 'Ho chinh sach', 'Khong', 'Khong', 'Hộ mẫu chính sách')
)

$personSampleRows = @(
  @('HD001', 'NK001', 'Nguyễn Văn An', 'Nam', '1950-05-12', '001050000001', '2018-04-01', 'Công an tỉnh', '0912345678', 'Chu ho', '', '', 'Kinh', 'Khong', 'Nguoi cao tuoi (70+)', 'Trung hoc', 'Da ket hon', 'Thuong tru', 'O nha', 'Con song', 'Thôn 9, Xã Minh Châu', 'Khong', 'Khong', 'Khong', 'Khong', 'Co', 'Co', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Co', '', '', 'Nguoi cao tuoi', '2026-01-01', '2026-12-31', 'Trạm y tế xã Minh Châu'),
  @('HD001', 'NK002', 'Nguyễn Thị Lan', 'Nu', '1955-07-22', '001055000002', '2018-04-01', 'Công an tỉnh', '0912345679', 'Vo', '', '', 'Kinh', 'Khong', 'Nghi huu', 'Trung hoc', 'Da ket hon', 'Thuong tru', 'O nha', 'Con song', 'Thôn 9, Xã Minh Châu', 'Khong', 'Khong', 'Co', 'Khong', 'Khong', 'Co', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Co', 'Co', 'HT3012345678901', 'Nguoi huong luong huu', '2026-01-01', '2026-12-31', 'Trạm y tế xã Minh Châu'),
  @('HD001', 'NK003', 'Nguyễn Văn Bình', 'Nam', '1988-03-10', '001088000003', '2020-06-15', 'Cục CSQLHC về TTXH', '0912345680', '', 'Nguyễn Văn An', 'Nguyễn Thị Lan', 'Kinh', 'Khong', 'Cong nhan', 'THPT', 'Da ket hon', 'Thuong tru', 'O nha', 'Con song', 'Thôn 9, Xã Minh Châu', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Co', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', '', '', '', '', '', ''),
  @('HD001', 'NK004', 'Phạm Thị Hoa', 'Nu', '1990-09-18', '001090000004', '2021-02-20', 'Cục CSQLHC về TTXH', '0912345681', '', '', '', 'Kinh', 'Khong', 'Lao dong tu do', 'THPT', 'Da ket hon', 'Thuong tru', 'O nha', 'Con song', 'Thôn 9, Xã Minh Châu', 'Khong', 'Khong', 'Co', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Co', 'Khong', 'Co', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Co', 'HG4012345678901', 'Ho gia dinh', '2026-01-01', '2026-12-31', 'Trạm y tế xã Minh Châu'),
  @('HD001', 'NK005', 'Nguyễn Minh Châu', 'Nu', '2013-11-05', '001113000005', '2025-08-10', 'Công an tỉnh', '', '', 'Nguyễn Văn Bình', 'Phạm Thị Hoa', 'Kinh', 'Khong', 'Hoc sinh', 'Hoc sinh', 'Chua ket hon', 'Thuong tru', 'O nha', 'Con song', 'Thôn 9, Xã Minh Châu', 'Khong', 'Co', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Co', 'Khong', 'Khong', '', '', 'Hoc sinh - Sinh vien', '2026-01-01', '2026-12-31', 'Trạm y tế xã Minh Châu'),
  @('HD002', 'NK006', 'Trần Thị Bình', 'Nu', '1975-02-14', '001075000006', '2019-03-12', 'Công an tỉnh', '0987654321', 'Chu ho', '', '', 'Kinh', 'Khong', 'Nong nghiep', 'THCS', 'Doc than', 'Thuong tru', 'O nha', 'Con song', 'Thôn 9, Xã Minh Châu', 'Khong', 'Khong', 'Co', 'Co', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Co', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', 'Khong', '', '', '', '', '')
)

$guideRows = @(
  @('Noi dung', 'Huong dan'),
  @('Dong du lieu', 'Nhap du lieu tu dong 2 cua sheet HoDan hoac NhanKhau. Khong doi ten sheet du lieu.'),
  @('Cot bat buoc ho dan', 'Ma ho, Dia chi. Ten chu ho nen nhap de ho so de tra cuu.'),
  @('Cot bat buoc nhan khau', 'Ma ho, Ho va ten, Ngay sinh. Ma ho phai ton tai truoc khi import nhan khau.'),
  @('Ngay thang', 'Dung dd/MM/yyyy hoac yyyy-MM-dd. CCCD, so dien thoai, ma ho, ma BHYT de dang Text.'),
  @('Cot Co/Khong', 'Nhap Co, Khong, 1, 0, X. Neu bo trong thi hieu la Khong. BHYT chi mac dinh Co theo cot Nghe nghiep khi la Hoc sinh hoac Nguoi cao tuoi (70+).'),
  @('Quan he voi chu ho', 'Co the de trong. Sau import he thong chi tu suy luan trong cung ho khi du du lieu bo/me/chu ho va khong ghi de gia tri da nhap.'),
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

function Write-Utf8NoBomText([string] $Path, [string] $Content) {
  $encoding = New-Object System.Text.UTF8Encoding $false
  [System.IO.File]::WriteAllText($Path, $Content, $encoding)
}

function Write-Utf8BomCsv([string] $Path, [string[]] $Headers, [object[]] $Rows) {
  $lines = @((ConvertTo-CsvLine $Headers))
  foreach ($row in $Rows) {
    $lines += (ConvertTo-CsvLine $row)
  }
  $content = ($lines -join "`r`n") + "`r`n"
  $encoding = New-Object System.Text.UTF8Encoding $true
  [System.IO.File]::WriteAllText($Path, $content, $encoding)
}

function XmlEscape([string] $Value) {
  if ($null -eq $Value) { return '' }
  $Value = [regex]::Replace($Value, '[\x00-\x08\x0B\x0C\x0E-\x1F]', '')
  return [System.Security.SecurityElement]::Escape($Value)
}

function Assert-RowWidths([string] $Name, [string[]] $Headers, [object[]] $Rows) {
  for ($i = 0; $i -lt $Rows.Count; $i++) {
    if ($Rows[$i].Count -ne $Headers.Count) {
      throw ("{0} sample row {1} has {2} cells, expected {3}." -f $Name, ($i + 1), $Rows[$i].Count, $Headers.Count)
    }
  }
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
  [void]$sb.AppendLine('<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>')
  [void]$sb.AppendLine('<sheetFormatPr defaultRowHeight="15"/>')
  if ($Widths.Count -gt 0) {
    [void]$sb.AppendLine('<cols>')
    for ($i = 0; $i -lt $Widths.Count; $i++) {
      $col = $i + 1
      [void]$sb.AppendLine(('<col min="{0}" max="{0}" width="{1}" customWidth="1"/>' -f $col, $Widths[$i]))
    }
    [void]$sb.AppendLine('</cols>')
  }
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

function Assert-XlsxPackage([string] $Path) {
  Add-Type -AssemblyName System.IO.Compression
  Add-Type -AssemblyName System.IO.Compression.FileSystem
  $zip = [System.IO.Compression.ZipFile]::OpenRead($Path)
  try {
    foreach ($entryName in @('xl/workbook.xml', 'xl/styles.xml', 'xl/worksheets/sheet1.xml', 'xl/worksheets/sheet2.xml', 'xl/worksheets/sheet3.xml')) {
      $entry = $zip.GetEntry($entryName)
      if ($null -eq $entry) { throw "XLSX package is missing $entryName" }
      $reader = New-Object System.IO.StreamReader($entry.Open(), [System.Text.Encoding]::UTF8)
      try {
        [xml]$xml = $reader.ReadToEnd()
      } finally {
        $reader.Close()
      }

      if ($entryName -like 'xl/worksheets/*') {
        $children = @($xml.worksheet.ChildNodes | Where-Object { $_.NodeType -eq [System.Xml.XmlNodeType]::Element } | ForEach-Object { $_.LocalName })
        $expectedOrder = @('sheetPr', 'dimension', 'sheetViews', 'sheetFormatPr', 'cols', 'sheetData', 'sheetProtection', 'protectedRanges', 'scenarios', 'autoFilter', 'sortState', 'dataConsolidate', 'customSheetViews', 'mergeCells', 'phoneticPr', 'conditionalFormatting', 'dataValidations', 'hyperlinks', 'printOptions', 'pageMargins', 'pageSetup', 'headerFooter', 'rowBreaks', 'colBreaks', 'customProperties', 'cellWatches', 'ignoredErrors', 'smartTags', 'drawing', 'legacyDrawing', 'legacyDrawingHF', 'picture', 'oleObjects', 'controls', 'webPublishItems', 'tableParts', 'extLst')
        $last = -1
        foreach ($child in $children) {
          $index = [Array]::IndexOf($expectedOrder, $child)
          if ($index -lt 0) { throw "$entryName has unexpected worksheet child <$child>" }
          if ($index -lt $last) { throw "$entryName has invalid worksheet child order near <$child>" }
          $last = $index
        }
        if (-not ($children -contains 'sheetData')) { throw "$entryName is missing sheetData" }
      }
    }
  } finally {
    $zip.Dispose()
  }
}

function Write-Xlsx([string] $Path, [string] $DataSheetName, [string[]] $Headers, [object[]] $Rows) {
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

  Write-Utf8NoBomText (Join-Path $workDir '[Content_Types].xml') @'
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
'@

  Write-Utf8NoBomText (Join-Path $workDir '_rels\.rels') @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
'@

  Write-Utf8NoBomText (Join-Path $workDir 'xl\workbook.xml') @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="$DataSheetName" sheetId="1" r:id="rId1"/>
    <sheet name="HuongDan" sheetId="2" r:id="rId2"/>
    <sheet name="DanhMuc" sheetId="3" r:id="rId3"/>
  </sheets>
</workbook>
"@

  Write-Utf8NoBomText (Join-Path $workDir 'xl\_rels\workbook.xml.rels') @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>
  <Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
'@

  Write-Utf8NoBomText (Join-Path $workDir 'xl\styles.xml') @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts>
  <fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0F766E"/><bgColor indexed="64"/></patternFill></fill></fills>
  <borders count="2"><border/><border><left style="thin"><color rgb="FFD9E2EC"/></left><right style="thin"><color rgb="FFD9E2EC"/></right><top style="thin"><color rgb="FFD9E2EC"/></top><bottom style="thin"><color rgb="FFD9E2EC"/></bottom></border></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="49" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="49" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyNumberFormat="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs>
  <cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
</styleSheet>
'@

  $dataWidths = @()
  foreach ($h in $Headers) { $dataWidths += [Math]::Min([Math]::Max($h.Length + 4, 14), 28) }
  $dataRows = @()
  $dataRows += ,$Headers
  foreach ($row in $Rows) {
    $dataRows += ,$row
  }
  Write-Utf8NoBomText (Join-Path $workDir 'xl\worksheets\sheet1.xml') (New-SheetXml -Rows $dataRows -Widths $dataWidths)
  Write-Utf8NoBomText (Join-Path $workDir 'xl\worksheets\sheet2.xml') (New-SheetXml -Rows $guideRows -Widths @(28, 96))
  Write-Utf8NoBomText (Join-Path $workDir 'xl\worksheets\sheet3.xml') (New-SheetXml -Rows $catalogRows -Widths @(28, 120))

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
  Assert-XlsxPackage $Path
}

function Write-HtmlXls([string] $Path, [string[]] $Headers, [object[]] $Rows) {
  $headerHtml = ($Headers | ForEach-Object { '<th>' + (XmlEscape $_) + '</th>' }) -join ''
  $rowHtml = ''
  foreach ($row in $Rows) {
    $cells = ($row | ForEach-Object { '<td>' + (XmlEscape $_) + '</td>' }) -join ''
    $rowHtml += '<tr>' + $cells + '</tr>'
  }
  $html = '<html><head><meta charset="utf-8"></head><body><table border="1"><tr>' + $headerHtml + '</tr>' + $rowHtml + '</table></body></html>'
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
    @{ Name = 'HoDan'; Headers = $householdHeaders; Rows = $householdSampleRows },
    @{ Name = 'NhanKhau'; Headers = $personHeaders; Rows = $personSampleRows }
  )) {
    [void]$sb.AppendLine(('  <Worksheet ss:Name="{0}"><Table>' -f $sheet.Name))
    [void]$sb.AppendLine('    <Row>')
    foreach ($header in $sheet.Headers) {
      [void]$sb.AppendLine(('      <Cell ss:StyleID="Header"><Data ss:Type="String">{0}</Data></Cell>' -f (XmlEscape $header)))
    }
    [void]$sb.AppendLine('    </Row>')
    foreach ($row in $sheet.Rows) {
      [void]$sb.AppendLine('    <Row>')
      foreach ($cell in $row) {
        [void]$sb.AppendLine(('      <Cell ss:StyleID="Text"><Data ss:Type="String">{0}</Data></Cell>' -f (XmlEscape $cell)))
      }
      [void]$sb.AppendLine('    </Row>')
    }
    [void]$sb.AppendLine('  </Table></Worksheet>')
  }
  [void]$sb.AppendLine('</Workbook>')
  $encoding = New-Object System.Text.UTF8Encoding $true
  [System.IO.File]::WriteAllText($Path, $sb.ToString(), $encoding)
}

New-Item -ItemType Directory -Force -Path $sampleDir | Out-Null
New-Item -ItemType Directory -Force -Path $tempRoot | Out-Null

Assert-RowWidths 'HoDan' $householdHeaders $householdSampleRows
Assert-RowWidths 'NhanKhau' $personHeaders $personSampleRows

Write-Utf8BomCsv (Join-Path $sampleDir 'import_household_template.csv') $householdHeaders $householdSampleRows
Write-Utf8BomCsv (Join-Path $sampleDir 'import_person_template.csv') $personHeaders $personSampleRows
Write-Utf8BomCsv (Join-Path $sampleDir 'import-template.csv') $personHeaders $personSampleRows

Write-Xlsx (Join-Path $sampleDir 'Mau_Import_HoDan.xlsx') 'HoDan' $householdHeaders $householdSampleRows
Write-Xlsx (Join-Path $sampleDir 'Mau_Import_NhanKhau.xlsx') 'NhanKhau' $personHeaders $personSampleRows

Write-HtmlXls (Join-Path $sampleDir 'Mau_Import_HoDan_ChinhQuyenSo.xls') $householdHeaders $householdSampleRows
Write-HtmlXls (Join-Path $sampleDir 'Mau_Import_NhanKhau_ChinhQuyenSo.xls') $personHeaders $personSampleRows
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
