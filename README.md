# Caldav Calendar Plugin for Roundcube

Yet another Caldav enabled Calendar plugin for Roundcube. This is a direct fork of the work over at kolab. There is a caldav driver folder now and
I am determined to get this working. So far it loads without error but as soon as I try to sync from Nextcloud sabre, I get the following error:


[17-Mar-2023 22:52:49 +0000]: <al215lem> PHP Error: DAV Error (400):
<html>
<head><title>400 Bad Request</title></head>
<body>
<center><h1>400 Bad Request</h1></center>
<hr><center>nginx</center>
</body>
</html>
 in /path_to_roundcube/plugins/libkolab/lib/kolab_dav_client.php on line 104 (GET /mail/?_task=calendar)
[17-Mar-2023 22:52:53 +0000]: <al215lem> PHP Error: Error loading template for libkolab.folderform in /home/gene/web/genesworld.net/public_html/mail/program/include/rcmail_output_html.php on line 804 (GET /mail/?_task=calendar&action=form-new&c%5Bid%5D=&_framed=1&_action=calendar)


Wish me luck and any input would be helpful.
  

:moneybag: **Donations** :moneybag:

If you use this plugin and would like to show your appreciation by buying me a cup of coffee, I surely would appreciate it.  
A regular cup of Joe is sufficient, but a Starbucks Coffee would be better ...  
Zelle (Zelle is integrated within many major banks Mobile Apps by default) - Just send to texxasrulez at yahoo dot com  
No Zelle in your banks mobile app, no problem, just click [Paypal](https://paypal.me/texxasrulez?locale.x=en_US) and I can make a Starbucks run ...

I appreciate the interest in this plugin and wish nothing but the best for all ...  
