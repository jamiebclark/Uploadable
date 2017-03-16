# FieldUpload
*FieldUpload* is a group of elements used to assist in uploading images and files with a Model

## Config
You can configure behaviors automatic settings by creating an `uploadable.php` file in your `app/Config/` folder. The basic version can be found in `app/Plugins/Uploadable/Config/uploadable.php`.
* *sizes*: Set a list of size aliases. If the alias is a key, then you can add an array value of parameters. There are a number of parameters you can add to each alias:
  * set: Hard-sets the size to [x,y]. Crops off any extra bits
  * setSoft: Hard-sets the size to [x,y]. Adds extra blank space around the image to make sure nothing is cropped
  * max: Shrinks an image so neither dimensions do not exceed [x,y]


## [FieldUploadBehavior](../../tree/master/Model/Behavior/FieldUploadBehavior.php)
You can use the behavior by including it in your Model's `$actsAs` array.
```
class Example extends AppModel {
  $actsAs = [
    'Uploadable.FieldUpload' => [
      'filename' => [],
    ]
  ];
 }
```
You must include an array of at least one existing field in the Model where the relative path of the image will be saved.

Each field can instead be a key, accepting an array of parameters. There are a number of settings you can specify:
* *dir* - The base upload directory
* *sizes* - An array of sizes. They'll be set in the _initFieldSettings method
* *root* - The file root. Defaults to CakePHP's webroot
* *isImage* - Whether the uploaded file is an image or not. Defaults to true
* *randomPath* - Whether a ranom path of folders (eg: "/01/05/72/") should be inserted between the path and filname. This is helpful for folders with many images
* *gitignore* - Makes sure an "empty" and ".gitignore" file are created in any upload directories	
* *default* - Path to a default image if a user does not specify one
* *plugin* - Whether or not the 
* *extensions* - Valid extensions

## Saving
In your Form, simply add the input as an input of type `file`:
```
echo $this->Form->create(null, ['type' => 'file']);  // Be sure to set the form type when opening the form
echo $this->Form->input("filename", ["type" => "file"]);
echo $this->Form->end("Save");
```
Alternately you can use the `FieldUploadImageHelper` to display additional options

## Finding
Before the Model result is returned, the FieldUpload Behavior will include additional information in an `uploadable` key in the returned result
```
$result = array(
  'Example' => array(
    'id' => 1,
    'title' => 'Example Title',
    'uploadable' => array(
      'filename' => array(
        'isDefault' => false,
        'sizes' => array(
          'large' => array(
            'path' => '/home/user/domain.com/app/webroot/img/examples/large/default.jpg', // The full path
            'src' => 'http://localhost/domain.com/img/examples/large/default.jpg',        // The HTTP path
            'mime' => 'image/jpeg',
            'extension' => 'jpg',
            'webroot' => '/home/user/domain.com/app/webroot/',  // The root
            'filesize' => (int) 43757,
            'modified' => (int) 1481041564,
            'width' => (int) 600,
            'height' => (int) 600
          ),
          'small' => array(
            'path' => '/home/user/domain.com/app/webroot/img/examples/large/default.jpg',
            'src' => 'http://localhost/domain.com/img/examples/large/default.jpg',
            'mime' => 'image/jpeg',
            'extension' => 'jpg',
            'webroot' => '/home/user/domain.com/app/webroot/',
            'filesize' => (int) 3868,
            'modified' => (int) 1481041564,
            'width' => (int) 80,
            'height' => (int) 80
          ),
        )
      )
    )
  )
);
```

## Displaying information using [FieldUploadImageHelper](../../tree/master/View/Helpers/FieldUploadImageHelper.php)
Using the `uploadable` information stored with the Behavior, we can output the information using the *FieldUploadImageHelper*.

Specifically we use the *image* method:
```
echo $this->FieldUploadImage->image($result['Example'], 'filename', 'small');
```
It requires: the find _data_, _the field where the path is stores_, and _the size alias_.

