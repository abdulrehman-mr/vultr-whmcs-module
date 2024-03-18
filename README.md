<h1>WHMCS server module for Vultr using the Vultr V2 API</h1>

Features of admin panel:
1) Admin will enter his API key in the settings
2) When he will go to add the VPS server product in admin panel to sell he can select Vultr Module as server provider it will load the the VPS plans from Vultr API to select system which will be used later to create instance.


Client Area Features:
After VPS Server purchase, in Client area if Status is Active of VPS in our system
On product details page first time after purchase if instance not created yet:
  - Ask client to choose the OS, load options using planID assigned to product from Vultr API
  - Ask client to select location of server, load options from Vultr API
  - Ask for hostname for the server
  - Create the instance
  - Save the instanceID, IP, username, default_password in system

Once instance is active:
  - Client can Start/Stop/Reboot/Reinstall Operating System on Server
  - Show client details like password, username, IP, OS, region of server from Vultr API

This Module is not ready yet you can contribute to it.

Contributers:
@abdulrehman-mr
@chatgpt
@anthropics

Made on VS Code, OB Controller , Edge , FireFox
