Collabora Online connector for Drupal
=====================================

This module integrate Collabora Online to Drupal. You can use it to
view and edit documents from within Drupal.


Installation
------------

_This section describes how to install the module in an existing Drupal website project.
For a local demo or test installation, [see below](#development--demo-installation)._

### Requirements

- Collabora Online server installed and running.
- Composer packages and Drupal modules as specified in `composer.json` and `collabora_online.info.yml`.

### Installation steps

See the [Drupal guide to install
modules](https://www.drupal.org/docs/extending-drupal/installing-modules).

To install on a project, user PHP composer:

```shell
composer require drupal/collabora_online
```

If you get an error saying `but it does not match your
minimum-stability`, you may need to adjust the `minimum-stability`
field in the `composer.json` of your project.

Then you can go into Drupal logged as an admin and go to _Extend_. In
the list you should be able to find _Collabora Online_ and enable it.

From there you can access the module specific configuration.

Please check the "Configuration" section below!


Development / demo installation
-------------------------------

A local demo and testing instance can be installed using docker-compose.

### Requirements

- [Docker](https://www.docker.com/get-docker)
- [Docker Compose](https://docs.docker.com/compose/)

### Installation steps

First, git clone the repository into a new directory, outside of any other Drupal project.

```sh
git clone https://github.com/CollaboraOnline/collabora-drupal.git collabora_online
```

Then run the following steps.

```sh
docker-compose up -d

docker-compose exec web composer install
docker-compose exec web ./vendor/bin/run drupal:site-install
```

Optionally, generate an admin login link.
```
docker-compose exec web ./vendor/bin/drush uli
```

The last command will output a link to the local website, with login for the admin account.

Otherwise, the local website will be available at http://web.test:8080/. The administrator login is 'admin'/'admin'.

### Configuration.

The demo instance is already fully configured.

See "Configuration" for optional customization.

### Using the demo instance

Minimal steps to see the editor in action:
- Log in as 'admin'/'admin'.
  - An menu with administrative links should appear at the top.
- Open "Content" > "Media" > "Add media" > "Document".
- Upload a simple *.docx or *.odt file, fill the required fields, and save.
- Back in the list of "Media entities" (`/admin/content/media`), click the media title.
- In the media page (e.g. `/media/1`), click the "View" button.
  - A panel with a Collabora editor in read-only mode should appear.
- Go back to "Content" > "Media" (`/admin/content/media`).
- Click the dropdown icon in the "Operations" column.
- Click "Edit in Collabora Online".
  - A Collabora editor should appear in a new page.
- Edit the document, then click the "Save" icon in the top left.

Advanced usage:
- Configure roles and permissions as in "User permissions" section below.
- Create a non-admin user with sufficient roles, login,

### Running the tests

To run the phpunit tests:

```bash
docker-compose exec web ./vendor/bin/phpunit
```


Configuration
-------------

_The configuration steps below are necessary to use the module in an existing Drupal website.
In the local development/demo installation, the manual configuration is optional._

Log into Drupal as an admin.

### JWT key

Go to _Configuration_ > _System_ > _Keys_

Click "Add key" to create a new key with:
- Key name: Any name, e.g. "Collabora Online".
- Key type: _JWT HMAC - Collabora Online_.
- Key provider: Please refer to the documentation of the 'key' module for security implications.
- Key value: You can generate a key with the shell command below.

```shell
head -c 64 /dev/urandom | base64 -w 0
```

### Collabora Online

Go to _Configuration_ > _Media_ > _Collabora Online Settings_

- _Collabora Online server URL_: the URL of the collabora online
  server. Note that you have to take into considerartion containers. If
  you run Drupal in one container and Collabora Online in another, you
  can not use `localhost`.
- _WOPI host base URL_: how the Collabora Online server can reach the
  Drupal server. Usually it is the public URL of this Drupal server.
- _JWT Private Key ID_: the id of the key created above.

Optional

- _Disable TLS certificate check for COOL_: If you configure a
  development server you might have self-signed certificate. Checking
  this is **INSECURE** but allow the drupal server to contact the
  collabora online server is the certificate doesn't check.
- _Access Token Expiration_: In second the expiration of the token to
  access the document. Default to 86400 seconds (24 hours).

### COOL

Some configuration changes might be necessary on the Collabora Online
side.

CSP for embedding must set properly to embed the Collabora Online
frame.

### Fields

To be able to attach document to Drupal content nodes, you need to
create a field.

Login as an admin, and got to the admnistration section.

- Go to _Structure_ > _Content types_
- Find the appropriate content and click _Manage fields_.
- Click _+ Create a new field_ (or if you already have created one you
  can re-use one)
- Enter a label, select _Media_, click _Continue_
- At the bottom for _Media type_, select _Document_. Click _Save
  Settings_. _Type of item to reference_ should have been set to the
  default value of _Media_.

You also must set the viewer for this kind of media.

- Go to _Structure_ > _Media Types_
- Select _Manage Display_ for _Document_
- In the _Field_ section, select in the _Format_ column, choose
  _Collabora Online Preview_.
- Click _Save_.

### User permissions

The module introduces permissions, which can be managed at
`/admin/people/permissions`.

The 'Administer the Collabora instance' permission grants
administrator access within the Collabora Online instance, when
Collabora is used within Drupal.  Most of the time this permission is
not needed, if the Collabora instance is configured from outside of
Drupal.

For each media type, the module introduces four permissions:
- "(media type): Edit any media file in Collabora"\
  Users with this permission are allowed to edit documents attached
  to a media entity of the given type, using the Collabora Online
  editor.
- "(media type): Edit own media file in Collabora"\
  Users with this permission are allowed to edit documents attached
  to a media entity of the given type, using the Collabora Online
  editor, if they are the owner/author of that media entity.
- "(media type): Preview published media file in Collabora"\
  Users with this permission are allowed to preview documents attached
  to a published media entity of the given type, using the Collabora
  Online editor in preview/readonly mode.
- "(media type): Preview own unpublished media file in Collabora"\
  Users with this permission are allowed to preview documents attached
  to an unpublished media entity of the given type, using the Collabora Online
  editor in preview/readonly mode.

In the current version of this module, the 'administer media' permission
from Drupal core grants access to all media operations, including the use
of the Collabora Online editor for preview and edit.

Developers can use entity access hooks to alter which users may edit
or preview media files in Collabora. This would allow to grant access
based on e.g. membership in a group.

### Views

The module integrates with Views by providing links as view fields, allowing
users to perform specific operations on documents directly from the view display.

These operations include actions such as "preview" and "edit," which can be
easily accessed through the generated links.

### Other configuration

If you need to change the accepted extensions to upload, go to
_Administration_ > _Structure_ > _Media Type_, for the line
_Documents_, click _Edit_, then click _Manage Fields_, and for the
right field, _Edit_:

- You can change the allowed file extensions.

To increase the maximum upload size (it is indicated on that page),
you need to increase the value in the PHP configuration.

Usually you can add a file `max_file_size.ini` (the name isn't much
important except its extension should be `.ini`) into
`/etc/php/conf.d/` (the path may be different) and put the following:

```
post_max_size = 30M
upload_max_filesize = 30M
```

These set the limits to a maximum of 30M. You can change as appropriate.

Sub-modules
-------------

### Collabora Online Group

Integration of Collabora Online with Group module. Check out the [README](/modules/collabora_online_group/README.md) of the module.

License
-------

This module is published under the MPL-2.0 license.
