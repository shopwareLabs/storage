def languages = params['languages'];

for (int i = 0; i < languages.length; i++) {
    def field_name = params['field'] + '.' + languages[i];

    if (doc[field_name].size() > 0 && doc[field_name].value != null) {
        return doc[field_name];
    }
}

return params['default'];
