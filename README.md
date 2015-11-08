#NSU Emu by SMX:
PREPARATION:
------------------
You need to download and install XAMPP. You can get it from https://www.apachefriends.org/download.html   
After you're done installing XAMPP, download and install Dual DHCP DNS Server http://sourceforge.net/projects/dhcp-dns-server/   
*NOTE: DualServer should work on *Unix like OSes too, but you can aswell use an alternative SW like dnsmasq   

CONFIGURATION:
-------------------
Open C:\Xampp\apache\conf\httpd.conf (or the equivalent on your OS) and add the following

```
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.*)\.laf$ $1.php [L]
```

This will redirect all requests to files with .laf extension to .php ones   
So for example: CheckSWManualUpdate.laf --> CheckSWManualUpdate.php   
  
Then search for "DocumentRoot" and change
```
/xampp/htdocs
```
with
```
/xampp/htdocs/nsu_emu
```

Open the XAMPP control panel and start/restart apache

-------------------

Go to your network adapter configuration and make sure the IP is set to static. Open the DNS configuration   
(on Windows it's under Adapter Settings, IPV4, Advanced, DNS) and make sure you have

* \<your LAN IP\>
* 8.8.8.8
* 8.8.4.4
* \<your Router IP\>

in the list, in that order. The lan ip will be used for custom hostnames, and the others will be used as Forwarding Servers   
for all other requests.

Open C:\DualServer\DualServer.ini (or the equivalent on your OS) and perform the following steps:   
under [DNS_HOSTS] section add:
```
snu.lge.com.usgcac.cdnetworks.net=\<your LAN IP\>
su.lge.com=<your LAN IP>
```

under [﻿WILD_HOSTS] section add:
```
su.lge.*=<your LAN IP>
snu.lge.*=<your LAN IP>
```

under [ALIASES] section add:
```
snu.lge.com.usgcac.cdnetworks.net=snu.lge.com
```

then under [RANGE_SET] section make sure that:   
* "DHCPRange" is a valid range in your subnet
* "SubmetMask" is not commented (comments are ';' or '#' symbols) and correct (usually 255.255.255.0 is ok)
* "DomainServer" is not commented and points to your LAN IP, e.g
```
DomainServer=<your LAN IP>
```
* "Router" is not commented and points to your LAN router, e.g
```
Router=192.168.0.1
```

Save the config and run "RunStandAlone.bat"  

Now you can check by opening a browser and going to "http://snu.lge.com" and "http://su.lge.com"  
It should point to the Apache directory listing with our files in

Now Create the new directory "nsu_emu" in "xampp/htdocs" and copy the files there  

If you're running on linux, run setup_dirs.sh to create the needed directories with the proper permissions  

NSU CONFIGURATION:
-------------------
edit server.cfg (check the file comments for details)  
create a folder named "epks" and store your epk files there  
eventually create a folder named "models" if you have custom hand-crafted response files in xml format you want to use (don't supply base64 encoded responses)  

If you want to make sure the configuration works, navigate to "http://127.0.0.1/upload.php" and you'll find a test page.  
You can use that page to **simulate** an LG TV that is requesting a FW update.  

Use the File chooser to select a REQUEST file (REQUEST, not RESPONSE). You can get Requests by using WireShark or checking the "requests" directory.  

The Emulator will dump all the requests there, so you can just check for FW update (even without server.cfg configured) and it will dump request  
