const profileButton = document.getElementById('profile');
const profileMenu = document.getElementById('profileMenu');
const body = document.querySelector('body');


function profile(action){
  if(action === 'open'){
    let rect = profileButton.getBoundingClientRect();
    profileMenu.style.left = rect.left-70+"px";
    profileMenu.style.top = rect.top+40+"px";
    profileMenu.style.display = 'block';
    profileButton.removeAttribute('onclick');
    body.setAttribute('onclick', "bodyClick('click', 'profile')");
  }
  else{
    profileMenu.style.display = 'none';
    profileButton.setAttribute('onclick', "profile('open')");
    body.removeAttribute('onclick');
  }
}

function bodyClick(action, menu){
  if(action == 'click'){
    if(menu == 'profile'){
      body.setAttribute('onclick', "profile('close')");
    }
  }
  else{
    body.removeAttribute('onclick');
  }
}
