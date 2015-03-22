Working with embedded documents
===============================

This extension does not provide any special way to work with embedded documents (sub-documents).
General recommendation is avoiding it if possible.
For example: instead of:

```
{
    content: "some content",
    author: {
        name: author1,
        email: author1@domain.com
    }
}
```

use following:

```
{
    content: "some content",
    author_name: author1,
    author_email: author1@domain.com
}
```

Yii Model designed assuming single attribute is a scalar. Validation and attribute processing based on this suggestion.
Still any attribute can be an array of any depth and complexity, however you should handle its validation on your own.

