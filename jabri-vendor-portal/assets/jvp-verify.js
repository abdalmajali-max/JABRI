
(function($){
  function setMsg($el, text, ok){
    $el.removeClass('jvp-error jvp-info').addClass(ok?'jvp-info':'jvp-error').text(text).show();
  }
  
  $(document).on('click', '#jvp-send-otp', function(e){
    e.preventDefault();
    var $btn=$(this), $phone=$('#jvp_phone'), $msg=$('#jvp-msg');
    var phone=$phone.val().trim(); 
    if(!phone){ 
      setMsg($msg,'أدخل رقم الواتساب',false); 
      return; 
    }
    $btn.prop('disabled',true);
    setMsg($msg, 'جاري إرسال الرمز...', true);

    $.ajax({
      url:JVPVARS.ajax, method:'POST',
      data:{ action:'jvp_send_phone_otp', phone:phone, _ajax_nonce:JVPVARS.nonce_send },
      success:function(j){
        if(j && j.success){ 
          setMsg($msg, (j.data&&j.data.message)||'تم الإرسال', true); 
          var $otpRow = $('#jvp-otp-row');
          if($otpRow.length){
            $otpRow.css('display', 'flex').hide().slideDown(200);
          }
        }
        else{ 
          setMsg($msg, (j&&j.data&&j.data.message)||'تعذّر الإرسال عبر Wawp', false); 
        }
      },
      error:function(){ setMsg($msg,'خطأ في الشبكة', false); },
      complete:function(){ $btn.prop('disabled',false); }
    });
  });

  $(document).on('click', '#jvp-verify-otp', function(e){
    e.preventDefault();
    var $btn=$(this), $phone=$('#jvp_phone'), $code=$('#jvp_otp_code'), $msg=$('#jvp-msg');
    var phone=$phone.val().trim(), code=$code.val().trim();
    if(!phone||!code){ setMsg($msg,'أدخل الهاتف والرمز', false); return; }
    $btn.prop('disabled',true);

    $.ajax({
      url:JVPVARS.ajax, method:'POST',
      data:{ action:'jvp_verify_phone_otp', phone:phone, code:code, _ajax_nonce:JVPVARS.nonce_verify },
      success:function(j){
        if(j && j.success){
          setMsg($msg, (j.data&&j.data.message)||'تم التحقق بنجاح', true);
          try{
            var regForm = document.getElementById('jvp-registration-form');
            if (regForm){
              var hidden = document.getElementById('jvp_phone_verified_flag');
              if (hidden){ hidden.value = '1'; }
              var details = document.getElementById('jvp-details-step');
              if (details){ details.style.display = 'block'; }
              var phoneInput = document.getElementById('jvp_phone');
              if (phoneInput){ phoneInput.readOnly = true; }
              $('#jvp-send-otp, #jvp-verify-otp').prop('disabled', true);
              $('#jvp-otp-row').slideUp(200);
            } else {
              setTimeout(function(){ window.location.href = JVPVARS.redirect; }, 900);
            }
          }catch(e){}
        }else{
          setMsg($msg, (j&&j.data&&j.data.message)||'رمز غير صحيح أو منتهي', false);
        }
      },
      error:function(){ setMsg($msg,'خطأ في الشبكة', false); },
      complete:function(){ $btn.prop('disabled',false); }
    });
  });
})(jQuery);
