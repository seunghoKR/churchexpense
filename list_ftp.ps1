$ftpHost = "115.68.168.215"
$ftpUser = "nuriohga"
$ftpPass = "seungho0409#"

$req = [System.Net.FtpWebRequest]::Create("ftp://$ftpHost/")
$req.Credentials = New-Object System.Net.NetworkCredential($ftpUser, $ftpPass)
$req.Method = [System.Net.WebRequestMethods+Ftp]::ListDirectoryDetails

$resp = $req.GetResponse()
$reader = New-Object System.IO.StreamReader($resp.GetResponseStream())
$output = $reader.ReadToEnd()
$reader.Close()
$resp.Close()

Write-Host "--- FTP ROOT DIRECTORY LIST ---"
Write-Host $output
