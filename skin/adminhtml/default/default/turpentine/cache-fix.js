
// When Turpentine caches (last two items) are ENABLED, the FPC option must OFF and not be clickable. Then the admins can never change FPC on or off.
// When the Turpentines caches are DISABLED, then the FPC option must be clickable.

document.observe("dom:loaded", function()
{
    if($('cache_type_turpentine_pages').innerHTML=='Enabled' || $('cache_type_turpentine_esi_blocks').innerHTML=='Enabled')
    {
        $$('input.massaction-checkbox').each(function(k,v)
        {
            if(k.value=='full_page')
            {
                k.remove();
                cache_grid_massactionJsObject.setGridIds('config,layout,block_html,translate,collections,eav,config_api,turpentine_pages,turpentine_esi_blocks');
            }
        });
    }
});
