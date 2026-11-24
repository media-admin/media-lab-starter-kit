# Kuratierte Tabler-Auswahl nach Kategorien (Reihenfolge = Reihenfolge im Editor)
CATS = [
("Werbemittel & Textilien", """shirt shirt-sport jacket hanger clothes-rack sock tie shoe flip-flops backpack beach-bag paper-bag shopping-bag basket
gift gift-card cup mug coffee bottle glass glass-full teapot balloon confetti christmas-ball christmas-tree trophy medal medal-2 award
badge ballpen pencil needle-thread umbrella umbrella-closed sunglasses key tag tags ticket sticker sticker-2 pennant flag candle lamp yarn
wash-machine ironing wash-eco laurel-wreath crown cake perfume cookie candy lollipop"""),
("Druck & Design", """printer palette brush paint color-swatch color-picker scissors cut ruler ruler-2 ruler-measure crop frame photo photo-edit picture-in-picture
layers-intersect stack-2 blend-mode droplet bucket-droplet typography typeface text-size letter-case letter-case-upper pencil-ruler edit vector
vector-bezier polygon shape shapes? grid-3x3 layout-grid layout-dashboard layout-cards template artboard background sparkles sparkle wand sketching
writing writing-sign signature file-vector file-typography file-type-pdf file-type-svg file-type-jpg file-type-png file-certificate copy resize
dimensions box-model aspect-ratio magnet mask gradienter grain noise-reduction opacity texture blur contrast brightness spray stroke-curved
align-left align-center align-right align-justified? bold italic underline h-1 h-2 list-details columns"""),
("Büro & Business", """briefcase briefcase-2 building-store building-factory building-skyscraper building-warehouse buildings building-bank building-community
home home-2 school book books notebook notes clipboard clipboard-check clipboard-list report report-analytics file-invoice invoice receipt receipt-2
certificate contract license rubber-stamp calculator calendar calendar-event calendar-time calendar-month calendar-week calendar-stats calendar-check
clock alarm hourglass stopwatch timeline target target-arrow chart-bar chart-line chart-pie chart-donut chart-area chart-funnel chart-histogram
chart-infographic trending-up trending-down presentation presentation-analytics businessplan affiliate users users-group user user-circle id-badge
friends heart-handshake bulb puzzle rocket gavel scale vault file-text files folder folders archive inbox paperclip bookmark news article"""),
("Kontakt & Kommunikation", """phone phone-call phone-calling phone-incoming phone-outgoing phone-done mail mail-opened mail-fast mail-forward mailbox email-stamp
send send-2 message message-2 message-circle messages message-dots message-reply message-language bubble bubble-text address-book map-pin map-pins
map-pinned location current-location map map-2 world world-www globe at link qrcode wifi antenna headset headphones microphone speakerphone bell
bell-ringing notification rss share share-2 external-link help-circle info-circle question-mark lifebuoy device-landline-phone device-mobile-message
chalkboard-teacher language"""),
("Social Media", """brand-facebook brand-instagram brand-linkedin brand-youtube brand-x brand-whatsapp brand-pinterest brand-tiktok brand-xing brand-vimeo
brand-telegram brand-google brand-threads brand-spotify brand-snapchat brand-skype brand-messenger brand-twitter brand-behance brand-dribbble
brand-github brand-figma brand-wordpress"""),
("Service & Prozess", """check checks circle-check checkbox checklist list-check progress refresh rotate loop arrows-exchange transfer settings settings-2 tool tools
adjustments filter search zoom-in zoom-out shield shield-check shield-lock lock lock-open key fingerprint heart thumb-up thumb-down star stars flag
bolt flame leaf recycle seedling world discount-check rosette-discount-check certificate-2 diamond crown eye download upload cloud cloud-upload
cloud-download user-check headset help-circle alert-triangle alert-circle bell calendar-check history restore replace wand sparkles trash
clipboard-text rosette rosette-discount discount percentage-50 pointer click hand-click progress-check"""),
("Versand & Lager", """truck truck-delivery truck-loading truck-return package packages package-export package-import box box-multiple forklift crane
building-warehouse plane ship train bus bike car route map-route gas-station container shopping-cart shopping-cart-plus basket-plus dolly? parking
road-sign traffic-cone steering-wheel trolley caravan sailboat helicopter motorbike scooter tractor ambulance firetruck bulldozer
barcode scan stack-push stack-pop"""),
("Natur & Umwelt", """leaf leaf-2 plant plant-2 seedling tree trees flower cactus feather mountain sun moon cloud cloud-rain cloud-snow cloud-storm cloud-fog
snowflake rainbow flame droplet droplets wind recycle home-eco battery-eco solar-panel wind-electricity sun-electricity planet butterfly paw fish
dog cat bug clover acorn wheat windmill beach tent campfire sunrise sunset-2 temperature temperature-snow temperature-sun uv-index tornado
volcano waves-electricity"""),
("Essen & Trinken", """coffee cup mug beer bottle glass glass-champagne glass-cocktail glass-gin cake cookie candy lollipop ice-cream pizza burger salad apple
banana carrot bread baguette cheese egg milk soup tools-kitchen-2 chef-hat bowl grill wheat lemon grape cherry avocado mushroom pepper salt
sausage meat bubble-tea microwave cooker whisk"""),
("Pfeile", """arrow-right arrow-left arrow-up arrow-down arrow-up-right arrow-up-left arrow-down-right arrow-down-left arrow-narrow-right arrow-narrow-left
arrow-narrow-up arrow-narrow-down arrow-big-right arrow-big-left arrow-big-up arrow-big-down chevron-right chevron-left chevron-up chevron-down
chevrons-right chevrons-left chevrons-up chevrons-down caret-right caret-left caret-up caret-down arrow-right-circle arrow-left-circle
arrow-up-circle arrow-down-circle arrow-right-tail arrow-right-bar arrow-bar-right arrow-back arrow-forward arrow-back-up arrow-forward-up
corner-down-right corner-up-right arrows-left-right arrows-up-down arrows-maximize arrows-minimize arrows-diagonal arrow-curve-right
arrow-ramp-right arrow-zig-zag arrow-bounce arrow-merge arrow-fork arrow-loop-right arrow-rotary-right arrow-roundabout-right u-turn-right
login logout swipe-right"""),
("Symbole & Zeichen", """plus minus x check circle square triangle hexagon diamond star heart hearts rosette percentage hash at asterisk-simple question-mark
exclamation-mark info-circle alert-circle alert-triangle ban copyright registered trademark command quote quotes infinity equal math-symbols
sum pi? tilde slashes dots dots-vertical menu-2 grip-vertical point octagon pentagon cube sphere cone pyramid cylinder prism capsule oval
circle-dot circle-dashed square-rounded category category-2 circles squares triangles heart-broken yin-yang peace om anchor"""),
("Medien & Marketing", """camera photo video movie player-play circle-play music microphone headphones radio speakerphone volume device-tv device-desktop
device-laptop device-mobile device-tablet device-watch qrcode screenshot polaroid aperture presentation seo ad ad-2 badge-ad share heart thumb-up
eye click hand-click pointer news article rss target-arrow award megaphone? playlist album vinyl waveform podcast? broadcast cast camera-selfie
focus-2 photo-video live-photo hdr user-screen subtitles keyframes chart-bar chart-line analyze"""),
("Menschen & Gesten", """user users users-group user-circle user-heart user-star user-plus user-check friends man woman baby-carriage mood-smile mood-happy
mood-wink mood-surprised mood-nerd mood-sad mood-neutral mood-smile-beam mood-tongue hand-click hand-stop hand-grab hand-finger hand-love-you
hand-two-fingers hand-three-fingers hand-move thumb-up thumb-down heart-handshake brain eye ear walk run yoga accessible cane old nurse
massage stretching-2 pray"""),
("Orte & Verkehr", """map-pin location current-location compass navigation directions route route-2 road road-sign flag pin world map
building-store building-factory building-hospital building-bank building-skyscraper building-church building-castle building-cottage
building-stadium building-lighthouse building-bridge buildings home school tent beach parking bus-stop traffic-lights fountain
tower stairs door-enter door-exit escalator elevator hotel-service luggage plane-departure plane-arrival train ferry"""),
("Sport & Freizeit", """ball-football ball-basketball ball-tennis ball-volleyball ball-baseball ball-american-football ball-bowling golf trophy medal olympics
dumbbell barbell bike yoga run walk swimming ski-jumping ice-skating skateboarding kayak karate bow ping-pong pool treadmill soccer-field
tournament scoreboard helmet guitar-pick piano dice puzzle sailboat parachute scuba-diving roller-skating rugby cricket chess meeple
play-card hula-hoop jump-rope trekking climbing? exercise-ball cliff-jumping curling waterpolo"""),
("Technik & Geräte", """device-desktop device-laptop device-mobile device-tablet device-watch device-tv device-speaker device-usb device-floppy
device-camera-phone device-cctv keyboard mouse printer router server wifi bluetooth battery-4 battery-charging plug plug-connected power cpu memory
qrcode barcode scan usb antenna phone calculator cloud code terminal-2 database api settings robot ai bulb circuit-bulb circuit-battery
device-gamepad device-airpods gpu network topology-star plug-x? shredder lawn-mower fridge blender air-conditioning wash-machine"""),
("Preise & Zahlen", """currency-euro currency-dollar coin-euro coin coins cash cash-banknote credit-card receipt-euro receipt-tax percentage discount
rosette-discount basket-discount shopping-cart-discount tag-dollar tax tax-euro moneybag wallet cash-register businessplan transaction-euro
number-0 number-1 number-2 number-3 number-4 number-5 number-6 number-7 number-8 number-9
circle-number-1 circle-number-2 circle-number-3 circle-number-4 circle-number-5 circle-number-6 circle-number-7 circle-number-8 circle-number-9
square-rounded-number-1 square-rounded-number-2 square-rounded-number-3 square-rounded-number-4 square-rounded-number-5"""),
]
