(function (wp) {
  if (!wp || !wp.blocks || !wp.element || !wp.blockEditor || !wp.components) return;
  var el = wp.element.createElement;
  var registerBlockType = wp.blocks.registerBlockType;
  var InspectorControls = wp.blockEditor.InspectorControls;
  var useBlockProps = wp.blockEditor.useBlockProps;
  var PanelBody = wp.components.PanelBody, TextControl = wp.components.TextControl, SelectControl = wp.components.SelectControl, ToggleControl = wp.components.ToggleControl;
  registerBlockType('kashida/video-embed', {
    edit: function (props) {
      var a = props.attributes, set = props.setAttributes;
      return el('div', useBlockProps(),
        el(InspectorControls, {}, el(PanelBody, {title:'Video settings', initialOpen:true},
          el(TextControl,{label:'Video URL',value:a.url,onChange:function(v){set({url:v});}}),
          el(SelectControl,{label:'Aspect ratio',value:a.ratio,options:[{label:'16:9',value:'16:9'},{label:'9:16',value:'9:16'},{label:'4:3',value:'4:3'},{label:'1:1',value:'1:1'}],onChange:function(v){set({ratio:v});}}),
          el(ToggleControl,{label:'Show player controls',checked:a.controls,onChange:function(v){set({controls:v});}}),
          el(ToggleControl,{label:'Lazy loading',checked:a.lazy,onChange:function(v){set({lazy:v});}})
        )),
        el('div',{style:{border:'1px solid #ddd',padding:'18px',borderRadius:'8px',background:'#fff'}},
          el('strong',{},'Kashida Video Embed'),
          el(TextControl,{label:'Paste video URL',value:a.url,onChange:function(v){set({url:v});},placeholder:'https://youtu.be/…'}),
          el('p',{},a.url ? 'Video URL saved. The player will render on the front end.' : 'Paste a supported video URL to embed it automatically.')
        )
      );
    },
    save: function(){return null;}
  });
})(window.wp);
