<?php namespace tool_stdlogarchiver\util;

trait persistent_soft_delete_trait{

    protected function before_delete() {
        if(static::has_property('deleted')){
            throw new coding_exception('The method delete() is not supported. Use soft_delete() instead.');
        }
    }

    /**
     * Soft deletes the record.
     * 
     * Call the hooks bellow, if implemented:
     *  - before_soft_delete()
     *  - after_soft_delete()
     *
     * @return boolean
     */
    public function soft_delete() : bool {
        global $DB;

        if ($this->raw_get('id') <= 0) {
            throw new coding_exception('id is required to delete');
        }

        if(method_exists($this, 'before_soft_delete')){
            $this->before_soft_delete();
        }

        $DB->set_field(static::TABLE, 'deleted', 1, ['id' => $this->raw_get('id')]);
        $this->raw_set('deleted', 1);

        if(method_exists($this, 'after_soft_delete')){
            $this->after_soft_delete();
        }

        return true;
    }
}