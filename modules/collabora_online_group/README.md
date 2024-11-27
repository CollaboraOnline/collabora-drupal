Collabora Online Group
=====================================

This submodule integrates the Group module (https://www.drupal.org/project/group)
by managing group-related permissions. It allows fine-grained control over user
access within groups, enabling specific permissions for Collabora content and
actions based on group membership.

### Requirements

- Groupmedia module: https://www.drupal.org/project/groupmedia
  - Compatible with versions 3.x and 4.x.

### User permissions

The module maps existing Collabora media operation permissions to the group type
instances.

#### Permissions:
- "(media type): Edit any media file in Collabora"
- "(media type): Edit own media file in Collabora"
- "(media type): Preview published media file in Collabora"
- "(media type): Preview own unpublished media file in Collabora"

Check [Collabora Online README](/README.md#user-permissions) for more information about permissions.

### Views

Additionally, the submodule modifies Groupmedia view configuration to add links
for Collabora operations.

License
-------

This module is published under the MPL-2.0 license.
