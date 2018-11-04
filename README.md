slackinvite Plugin for DokuWiki

Includes file upload into wikipages depending on the user rights.

If you install this plugin manually, make sure it is installed in
lib/plugins/slackinvite/ - if the folder is called different it
will not work!

To use plugin:

Copy secrets.php.template into a php file called secrets.php, and fill in your secret information. 
This file is gitignored and will not be pushed to keep your tokens safe.

Where to find tokens:
reCaptcha Client + Secret tokens: https://www.google.com/recaptcha/admin#list

Slack invite auth token: https://get.slack.help/hc/en-us/articles/215770388

Slack Hostname is the name of the workspace, for example the hostname for the slack group slackgroup.slack.com is slackgroup

Slack default channels to join can be set to 'C04MB1CAH' which is #general, it will add all normal default channels.

After that insert ` {slackinvite} `(CASE SENSITIVE) on a page.  A form will appear.
After filling out name and email address, click submit.  If successful,
you should get an invite to join the your slack team in the channel you specified.

----

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; version 2 of the License

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

See the COPYING file in your DokuWiki folder for details
