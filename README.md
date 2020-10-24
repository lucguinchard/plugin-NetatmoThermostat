# plugin-netatmoThermostat

## Installation

Pour faire des manipulations sur la base SQL (jee|next)dom : https://<host>/index.php?v=d&p=database

Pour passer à cette version : il faut mettre à jour la table `update` :
```SQL
UPDATE `update` SET `configuration`='{"user":"lucguinchard","repository":"plugin-NetatmoThermostat","version":"master"}', `source`='github' WHERE `name` = 'netatmoThermostat';
```
Ensuite il faut mettre à jour le plugin via l’outil de mise à jour de (jee|next)dom.

## Revenir sur la version du `market`

Pour revenir à la version du `market jeedom` voici la requête :
```SQL
UPDATE `update` SET `configuration`='{"version":"stable","market":1,"third_plugin":1,"doNotUpdate":"0"}', `source`='market' WHERE `name` = 'netatmoThermostat';
```

