# drupal-config-devel

## Add the 'override' folder support.

For example mymodule.info.yml:

```yaml
config_devel:
  install:
    - node.type.page
  override:
    - field.storage.node.body
```
