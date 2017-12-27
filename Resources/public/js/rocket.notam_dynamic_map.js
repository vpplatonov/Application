(function($) {

  $('document').ready(function() {
    Rocket.RocketNotamDynamicMap.attach();
  });

  Rocket = {
    settings: {
      notam: {
        dynamic_map: {
          info: null,
          lat: 52.389477,
          lng: 4.917577,
          icon: '/img/warning-icon-th.png',
          zoom: 10,
          marker_animation: 'DROP',
          type: 'google.maps.MapTypeId.ROADMAP',
          style: '[{"stylers":[{"saturation": 30}]}]'
        },
        code_allowed: ['EGLL', 'EGGW', 'EGLF', 'EGHI', 'EGKA', 'EGMD', 'EGMC', 'KLAX', 'SBSP']
      }
    }
  };

  Rocket.RocketNotamDynamicMap = {
    dynamic_map: null,
    markers_obj: [],
    infoWindow: null,
    attach: function(context, settings) {
      var self = this;

      $('.notam-get-code').not('.notam-processed').addClass('notam-processed').on('click', function(e) {
        e.preventDefault();
        var entered = $('input[name="icao"]').val(),
            code_array = entered.split(',');

        // Some validation & error message.
        for (var code in code_array) {
          if (Rocket.settings.notam.code_allowed.indexOf(code_array[code]) == -1) {
            $('.notam-error-messsage').text('Defetive code entered: ' + code_array[code]).addClass('alert alert-warning');
            return;
          }
        }

        $.ajax({
          data: {
            icao: entered || 'EGKA',
          },
          type: 'POST',
          url: 'rocket/icao',
          dataType: 'json'
        }).always(function () {

        }).done(function (data) {
          if (data && !('error' in data)) {
            $('.notam-error-messsage').text('').removeClass('alert alert-warning');
            Rocket.settings.notam.dynamic_map.members = data;
            self.renewMarkers();
          }
          else {
            $('.notam-error-messsage').text(data.error).addClass('alert alert-warning');
          }
        });

      });

      $('#rocket-notam-dynamic-map').not('.map-processed').addClass('map-processed').each(function() {
        var target_point = new google.maps.LatLng(Rocket.settings.notam.dynamic_map.lat, Rocket.settings.notam.dynamic_map.lng),
            mapOptions = {
              zoom: parseInt(Rocket.settings.notam.dynamic_map.zoom),
              maxZoom: 12,
              center: target_point,
              mapTypeId: eval(Rocket.settings.notam.dynamic_map.type),
              mapTypeControl: false,
              panControl: false,
              zoomControl: true,
              zoomControlOptions: {
                style: google.maps.ZoomControlStyle.SMALL,
                position: google.maps.ControlPosition.LEFT_CENTER
              },
              streetViewControl: false
            };

        self.markers_obj = [];
        self.dynamic_map = new google.maps.Map(document.getElementById("rocket-notam-dynamic-map"), mapOptions);

        if (Rocket.settings.notam.dynamic_map.style != "") {
          self.dynamic_map.setOptions({styles: eval(Rocket.settings.notam.dynamic_map.style)});
        }

        // Create a single instance of the InfoWindow object which will be shared
        // by all Map objects to display information to the user.
        self.infoWindow = new google.maps.InfoWindow();

        // Make the info window close when clicking anywhere on the map.
        google.maps.event.addListener(self.dynamic_map, 'click', self.closeInfoWindow);

        // Magic here.
        self.renewMarkers();

        return false;
      });
    },
    renewMarkers: function() {
      var self = this;

      if (!$.isEmptyObject(this.markers_obj)) {
        for (var i in this.markers_obj) {
          self.RocketDynMapMarkerRemove(i, false);
        }
      }

      if ($.isPlainObject(Rocket.settings.notam) && $.isPlainObject(Rocket.settings.notam.dynamic_map)
        && "members" in Rocket.settings.notam.dynamic_map
        && !$.isEmptyObject(Rocket.settings.notam.dynamic_map.members)) {

        for (var member in Rocket.settings.notam.dynamic_map.members) {
          self.RocketDynMapMarkerAdd(Rocket.settings.notam.dynamic_map.members[member]['nid'], false);
        }
        self.RocketDynMapMarkersVisible();
        // Clear for next requests.
        Rocket.settings.notam.dynamic_map.members = {};
      }
    },
    closeInfoWindow: function() {
      Rocket.RocketNotamDynamicMap.infoWindow.close();
      Rocket.RocketNotamDynamicMap.infoWindow.setPosition(null);
    },
    openInfoWindow: function(marker, info) {
      var latlng = this.infoWindow.getPosition(),
          pos = marker.getPosition();

      if (latlng && latlng.lat == pos.lat && latlng.lng == pos.lng) {
        this.closeInfoWindow();
      }
      else {
        this.infoWindow.setContent(info);
        marker.setZIndex(google.maps.Marker.MAX_ZINDEX + 1);
        this.infoWindow.open(this.dynamic_map, marker);
      }
    },
    RocketDynMapMarkerFind: function(uid) {
      var marker_info = {}, member;

      if ("members" in Rocket.settings.notam.dynamic_map) {
        member = Rocket.settings.notam.dynamic_map.members[uid];
        marker_info['latlng'] = [];
        if ($.isPlainObject(member['location'])) {
          marker_info['latlng'][0] = member['location']['latitude'];
          marker_info['latlng'][1] = member['location']['longitude'];
        }
        else if (typeof member['location'] == 'string') {
          marker_info['latlng'] = member['location'].split(',');
        }
        else {
          return {};
        }
        marker_info['title'] = member['title'];
      }
      return marker_info;
    },
    RocketDynMapMarkerAdd: function(uid, bounds) {
      var self = this, markerOptions = {}, marker_info = [];

      if (this.dynamic_map) {
        if (this.markers_obj[uid]) {
          this.markers_obj[uid].setMap(this.dynamic_map);
        }
        else {

          marker_info = self.RocketDynMapMarkerFind(uid);
          if ($.isEmptyObject(marker_info)) {
            return;
          }

          markerOptions = {
            optimized: false,
            position: new google.maps.LatLng(marker_info['latlng'][0], marker_info['latlng'][1]),
            draggable: false,
            map: this.dynamic_map,
            icon: {
              url: Rocket.settings.notam.dynamic_map.icon,
              scaledSize: new google.maps.Size(33, 30),
              origin: new google.maps.Point(0, 0),
              anchor: new google.maps.Point(16, 20)
            },
            shape: {
              coords: [1, 33, 15, 1, 30, 33],
              type: 'poly'
            }
          };

          if (bounds) {
            markerOptions['animation'] = google.maps.Animation.DROP;
          }

          this.markers_obj[uid] = new google.maps.Marker(markerOptions);

          // Register event listeners to each marker to open a shared info
          // window displaying the marker's position when clicked or dragged.
          addPopUpMarker(this.markers_obj[uid], marker_info['title']);
        }
        if (bounds) {
          this.RocketDynMapMarkersVisible();
        }
      }

      function addPopUpMarker(marker, info) {
        google.maps.event.addListener(marker, 'click', function() {
          self.openInfoWindow(marker, info);
        });
      }
    },
    RocketDynMapMarkerRemove: function(uid, bounds) {
      // Remove marker from map.
      if (this.markers_obj[uid]) {
        this.markers_obj[uid].setMap(null);
        if (bounds) {
          this.RocketDynMapMarkersVisible();
        }
      }
    },
    RocketDynMapMarkersVisible: function() {
      // Make all markers visible.
      var j = 0, bounds = new google.maps.LatLngBounds();

      for (var i in this.markers_obj) {
        if (this.markers_obj[i].getMap()) {
          bounds.extend(this.markers_obj[i].getPosition());
          j++;
        }
      }
      this.dynamic_map.fitBounds(bounds);
    }
  };

})(jQuery);
