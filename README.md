# ES客户端 - hyperf版本

### Composer

```
composer require janartist/elasticsearch
```

### Model

* index 相当于mysql中的表

```php
<?php

declare(strict_types=1);

namespace App\EsModel;

use Janartist\Elasticsearch\Model;

class OrderModel extends Model
{
    /**
     * 索引
     * */
    protected $index = 'chungou-order';
    /**
     * 字段类型，创建索引中用
     * */
    protected $casts = ['name' => [
        "type" => "text",
        "analyzer" => "ik_max_word",
        "search_analyzer" => "ik_smart"
    ]];
}
```

### 查询

```php
<?php
   OrderModel::query()->where('name', 'zhangsan')->get();
   OrderModel::query()->where('name', 'zhangsan')->first();  
   OrderModel::query()->whereIn('name', ['zhangsan'])->first();        
   OrderModel::query()->whereLike('name', 'zhangsan')->first();  
   OrderModel::query()->find();       

```
### 新增修改删除

```php
<?php
     OrderModel::query()->create([]);
     OrderModel::query()->insert([[],[]]);
     OrderModel::query()->delete([], '23asdq');
     OrderModel::query()->update([], '23asdq');
```