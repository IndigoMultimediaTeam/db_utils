[![LTS+sub-branches](https://img.shields.io/badge/submodule-LTS+sub--branches-informational?style=flat-square&logo=git)](https://github.com/IndigoMultimediaTeam/lts-driven-git-submodules)

# db_utils
Nadstavba nad `cMySQL` a `cQuery`.

## Získání/nastavení pro pužití ve vl. projektu
**Získání**:
```bash
cd TARGET_PATH
git submodule add -b main --depth=1 git@github.com:IndigoMultimediaTeam/db_utils.git
```
… více o submodulech v [`git submodule`](https://gist.github.com/jaandrle/b4836d72b63a3eefc6126d94c683e5b3). V případě „negit” projektu, stačí prostě klasicky nakolnovat.

Po `require_*`, před použitím je potřeba namapovat databázi (typicky instanci `cMySQL`):
```php
<?php
$__DB = new cMySQL($__DB_CONNECT_STRING); //viz typicky `kerner/kernel.php`
require_once 'db_utils/db_utils.php';
db_utils::setDB($__DB);
```

## Použití
Třída umožňuje řetězení:
```php
<?php
db_utils::query(/* A */)->/* B */->execute()->/* C */;
```
… kde:
- **A**: Klasický MySQL dotaz podporující „statické proměnné” *::promenna::* a „dynamické” *::index::* známé z `cQuery`.
- **B**: Metody `set`/`map`/`setJoinComma` pro práci se „statickými proměnnými”.
- **C**: Metody známé z `cQuery`.

## Příklady
```php
<?php
$rows= db_utils::query("SELECT ::cols:: FROM `table` AS T")
	->setJoinComma('cols', array('id','name'), '`', 'T') //= SELECT T.`id`, T.`name` FROM `table` AS T
	->execute()
	->Rows();
```
```php
<?php
$q_rows= db_utils::query("SELECT ::cols:: FROM `table` AS T")->freeze();
$row_id= $q_rows->set('cols', 'id')->execute()->Rows();
$row_all= $q_rows->set('cols', '*')->execute()->Rows();
```
```php
<?php
$row= db_utils::query("SELECT * FROM `table` WHERE id=::0::")
	->execute(5)
	->Row();
```

## Testování
```php
<?php
db_utils::setDB(db_utils::debugDB());
$row= db_utils::query("SELECT * FROM `table` WHERE id=::0::")
	->execute(5)
	->Row();
```
```php
<?php
$row= db_utils::query(db_utils::debugDB(), "SELECT * FROM `table` WHERE id=::0::")
	->execute(5)
	->Row();
```
