NSH - 2021.10.05

Client for browsing Nomad DAV repositories.

Install
-------

NSH requires 'requests'(1).
Please refer to requests docs on how to install it (2)

Extract somewhere and launch nsh
If installing in an alternatte location, copy the util/py directory to the
directory containing the nsh script


Description
-----------

You can connect to a repository using

connect username@hostname

if you know a username on that site and if they have given you the requisite permission *or* their directory contains publicly readable content. 

----
NSH is a command line WebDAV client for Nomad platforms.
It knows how to magic-auth to remote hubs using OpenWebAuth.

NSH uses 'easywebdav' library (0) with small modifications
to 'zotify' it. (See easywebdav/LICENSE)



Commands
--------


connect <hostname>
	Authenticate to 'hostname' and switch to it. The root directory may be
hidden/empty. If it is, the only way to proceed is if you know a username on
that server. Then you can 'cd username'. 

connect <username@hostname>
	Authenticate to 'hostname' and switch to it and automatically cd to the 'username' directory
	
cd <dirname|..>
	change remote dir

ls [path] [-a] [-l] [-d]
	list remote files in current dir if 'path' not defined
	-a list all, show hidden dot-files
	-l list verbose
	-d list only dirs

exists <path>
	Check existence of 'path'
	
mkdir <name>
	Create directory 'name'

mkdirs <path>
	Create parent directories to path, if they don't exist

rmdir <name>
	Delete directory 'name'

delete <path>
	Delete file 'path'

put <local_path> [remote_path]
	Upload local file 'local_path' to 'remote_path'

get <remote_path> [local_path]
	Download remote file 'remote_path' and save it as 'local_path'

cat <remote_path>
	Print content of 'remote_path'

pwd
	Print current path

lcd
lpwd
lls
	Local file management (cd, pwd, and ls)

quit
help



Config
------

Create a .nshrc file in your home or in same folder with the nsh script:


	[nsh]
	host = https://yourhost.com/
	username = your_username
	password = your_password


Optionally adds

        verify_ssl = false

to skip verification of ssl certs


Changelog
----------
2021.10.06	Add alternate configuration support and cmdline arg processing
2021.10.05	Add autocompletion

0.0.3		Convert to python3 and rename from zotsh to nsh

0.0.2		Fix "CommandNotFound" exception, new 'cat' command

0.0.1		First release


Links
-----

_0 : https://github.com/amnong/easywebdav
_1 : http://docs.python-requests.org/en/latest/
_2 : http://docs.python-requests.org/en/latest/user/install/
