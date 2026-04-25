/**
 * WDB Membership System - Translation Module
 * Supports: English (en), Afaan Oromoo (om), Amharic (am), Tigrinya (ti)
 */

const translations = {
    en: {
        // Page Title
        'page.title': 'WDB Registration Portal',
        'page.subtitle': 'Join Waldaa Duuka Bu\'ootaa • Ethiopian Orthodox Tewahedo Church',
        'register.member': 'Register New Member',
        
        // Navigation
        'nav.home': 'Home',
        'nav.login': 'Login',
        'nav.register': 'Register',
        'nav.about': 'About',
        
        // Form Sections
        'section.personal': 'Personal Information',
        'section.clergy': 'Clergy Status (Haala Aangoo Lubummaa)',
        'section.clergy.subtitle': 'If you are ordained clergy, please provide your information and verification documents',
        'section.clergy.optional': 'Optional',
        'section.photo': 'Membership Photo',
        'section.photo.subtitle': 'Upload a clear, recent photo for your membership ID card',
        
        // Form Fields
        'field.fullname': 'Full Name',
        'field.fullname.placeholder': 'Your Full Name (First, Middle, Last)',
        'field.email': 'Email Address',
        'field.email.placeholder': 'e.g. Name@example.com',
        'field.username': 'Choose Username',
        'field.username.placeholder': 'Username (First 8 characters)',
        'field.password': 'Password',
        'field.password.placeholder': 'Your Password',
        'field.phone': 'Mobile Phone',
        'field.phone.placeholder': '+251 XXX XXX XXX',
        'field.gender': 'Gender',
        'field.gender.select': 'Select Gender',
        'field.gender.male': 'Male',
        'field.gender.female': 'Female',
        'field.gender.other': 'Other',
        'field.membership.date': 'Today\'s Date Membership Start Date',
        
        // Clergy Fields
        'field.diaconate.year': 'Diaconate Year',
        'field.diaconate.year.placeholder': 'e.g. 2015',
        'field.diaconate.church': 'Diaconate Church',
        'field.diaconate.church.placeholder': 'Church name',
        'field.priesthood.year': 'Priesthood Year',
        'field.priesthood.year.placeholder': 'e.g. 2018',
        'field.priesthood.church': 'Priesthood Church',
        'field.priesthood.church.placeholder': 'Church name',
        'field.monastic.year': 'Monastic Year',
        'field.monastic.year.placeholder': 'e.g. 2020',
        'field.monastic.kawaala': 'Monastic Kawaala',
        'field.monastic.kawaala.placeholder': 'Kawaala name',
        
        // File Upload
        'upload.clergy.title': 'Clergy Documents (Photo/PDF)',
        'upload.clergy.subtitle': 'Optional, max 5MB',
        'upload.clergy.prompt': 'Click to Upload or Drag & Drop',
        'upload.clergy.description': 'Upload ordination certificate or verification documents',
        'upload.clergy.button': 'Choose Document',
        'upload.clergy.requirements': 'Accepted Documents:',
        'upload.clergy.req1': 'Ordination certificate (Diaconate/Priesthood/Monastic)',
        'upload.clergy.req2': 'Church verification letter',
        'upload.clergy.req3': 'Photo of ordination ceremony',
        'upload.clergy.req4': 'Formats: JPG, PNG, PDF',
        'upload.clergy.req5': 'Maximum size: 5MB',
        
        'upload.photo.title': 'Upload Your Photo',
        'upload.photo.subtitle': 'Required, max 5MB',
        'upload.photo.prompt': 'Click to Upload or Drag & Drop',
        'upload.photo.description': 'Upload a clear photo of yourself',
        'upload.photo.button': 'Choose Photo',
        'upload.photo.requirements': 'Photo Requirements:',
        'upload.photo.req1': 'Clear, recent photo (passport-style preferred)',
        'upload.photo.req2': 'Face clearly visible, looking at camera',
        'upload.photo.req3': 'Good lighting, plain background',
        'upload.photo.req4': 'Formats: JPG, PNG, GIF',
        'upload.photo.req5': 'Maximum size: 5MB',
        'upload.photo.req6': 'Minimum resolution: 300x300 pixels',
        
        // Preview
        'preview.document': 'Document Preview',
        'preview.photo': 'Photo Preview',
        'preview.remove': 'Remove',
        'preview.filename': 'File Name:',
        'preview.filesize': 'File Size:',
        'preview.filetype': 'File Type:',
        'preview.dimensions': 'Dimensions:',
        'preview.status': 'Status:',
        'preview.ready': 'Ready',
        'preview.pdf': 'PDF Document',
        'preview.image': 'Image',
        
        // Buttons
        'button.submit': 'Sign Up and Join',
        'button.submitting': 'Creating Account...',
        
        // Messages
        'message.required': 'Required',
        'message.optional': 'Optional',
        'message.security': 'Your information is encrypted and secure',
        'message.already.account': 'Already have an account?',
        'message.signin': 'Sign in here',
        
        // Errors
        'error.file.size': 'File size must not exceed 5MB',
        'error.file.type.image': 'Please upload an image file (JPG, PNG, GIF)',
        'error.file.type.doc': 'Please upload an image (JPG, PNG) or PDF file',
        'error.resolution.low': 'Photo resolution is low. Minimum 300x300 pixels recommended.',
        
        // Formats
        'format.accepted': 'Accepted formats:',
        'format.jpg.png.gif': 'JPG, PNG, GIF (max 5MB)',
        'format.jpg.png.pdf': 'JPG, PNG, PDF (max 5MB)',
        
        // Dashboard Common
        'dashboard.welcome': 'Welcome',
        'dashboard.member': 'Member Dashboard',
        'dashboard.admin': 'Admin Dashboard',
        'dashboard.superadmin': 'Super Admin Dashboard',
        'dashboard.logout': 'Logout',
        'dashboard.profile': 'Profile',
        'dashboard.settings': 'Settings',
        'dashboard.home': 'Home',
        'dashboard.members': 'Members',
        'dashboard.announcements': 'Announcements',
        'dashboard.contributions': 'Contributions',
        'dashboard.reports': 'Reports',
        'dashboard.centers': 'Centers',
        'dashboard.users': 'Users',
        'dashboard.analytics': 'Analytics',
        
        // Member Dashboard
        'member.myprofile': 'My Profile',
        'member.mycontributions': 'My Contributions',
        'member.announcements': 'Announcements',
        'member.resources': 'Resources',
        'member.memberid': 'Member ID',
        'member.status': 'Status',
        'member.active': 'Active',
        'member.inactive': 'Inactive',
        'member.pending': 'Pending',
        'member.photo': 'Member Photo',
        'member.changephoto': 'Change Photo',
        'member.uploadphoto': 'Upload Photo',
        
        // Admin Dashboard
        'admin.managemembers': 'Manage Members',
        'admin.managecenters': 'Manage Centers',
        'admin.viewreports': 'View Reports',
        'admin.createannouncement': 'Create Announcement',
        'admin.totalmembers': 'Total Members',
        'admin.activecenters': 'Active Centers',
        'admin.pendingapprovals': 'Pending Approvals',
        'admin.recentactivity': 'Recent Activity',
        
        // Language Switcher
        'lang.english': 'English',
        'lang.oromoo': 'Afaan Oromoo',
        'lang.select': 'Select Language',
        
        // AI Assistant
        'assistant.title': 'WDB ONLINE ASSISTANT',
        'assistant.subtitle': '24/7 • Always here to help',
        'assistant.input.placeholder': 'Ask me anything about WDB...'
    },
    
    om: {
        // Page Title
        'page.title': 'Galmee Miseensummaa WDB',
        'page.subtitle': 'Waldaa Duuka Bu\'ootaa • Mana Amantaa Ortodoksii Tawaahedoo Itoophiyaa',
        'register.member': 'Miseensa Haaraa Galmeessi',
        
        // Navigation
        'nav.home': 'Mana',
        'nav.login': 'Seeni',
        'nav.register': 'Galmaa\'i',
        'nav.about': 'Waa\'ee',
        
        // Form Sections
        'section.personal': 'Odeeffannoo Dhuunfaa',
        'section.clergy': 'Haala Aangoo Lubummaa',
        'section.clergy.subtitle': 'Yoo aangoo lubummaa qabdan, odeeffannoo fi ragaalee mirkaneessuu keessan kennu',
        'section.clergy.optional': 'Dirqama Miti',
        'section.clergy': 'Haala Aangoo Lubummaa',
        'section.photo': 'Suuraa Miseensummaa',
        'section.photo.subtitle': 'Suuraa ifa ta\'e, yeroo dhiyoo kaafame kaardii eenyummaa miseensummaa keessaniif ol kaa\'aa',
        
        // Form Fields
        'field.fullname': 'Maqaa Guutuu',
        'field.fullname.placeholder': 'Maqaa Guutuu Keessan (Jalqaba, Gidduu, Dhumaa)',
        'field.email': 'Teessoo Email',
        'field.email.placeholder': 'fkf. Maqaa@fakkenya.com',
        'field.username': 'Maqaa Fayyadamaa Filadhu',
        'field.username.placeholder': 'Maqaa Fayyadamaa (Qubee 8 jalqabaa)',
        'field.password': 'Jecha Icciitii',
        'field.password.placeholder': 'Jecha Icciitii Keessan',
        'field.phone': 'Bilbila Harkaa',
        'field.phone.placeholder': '+251 XXX XXX XXX',
        'field.gender': 'Saala',
        'field.gender.select': 'Saala Filadhu',
        'field.gender.male': 'Dhiira',
        'field.gender.female': 'Dhalaa',
        'field.gender.other': 'Kan Biraa',
        'field.membership.date': 'Guyyaa Har\'aa Guyyaa Jalqaba Miseensummaa',
        
        // Clergy Fields
        'field.diaconate.year': 'Bara Diyaakoonummaa',
        'field.diaconate.year.placeholder': 'fkf. 2015',
        'field.diaconate.church': 'Mana Amantaa Diyaakoonummaa',
        'field.diaconate.church.placeholder': 'Maqaa mana amantaa',
        'field.priesthood.year': 'Bara Qeesummaa',
        'field.priesthood.year.placeholder': 'fkf. 2018',
        'field.priesthood.church': 'Mana Amantaa Qeesummaa',
        'field.priesthood.church.placeholder': 'Maqaa mana amantaa',
        'field.monastic.year': 'Bara Monoksummaa',
        'field.monastic.year.placeholder': 'fkf. 2020',
        'field.monastic.kawaala': 'Kawaala Monoksummaa',
        'field.monastic.kawaala.placeholder': 'Maqaa Kawaala',
        
        // File Upload
        'upload.clergy.title': 'Ragaalee Lubummaa (Suuraa/PDF)',
        'upload.clergy.subtitle': 'Dirqama miti, hanga 5MB',
        'upload.clergy.prompt': 'Ol Kaa\'uuf Cuqaasi ykn Harkisii Kaa\'i',
        'upload.clergy.description': 'Ragaa aangoo lubummaa ykn ragaalee mirkaneessuu ol kaa\'aa',
        'upload.clergy.button': 'Galmee Filadhu',
        'upload.clergy.requirements': 'Ragaalee Fudhataman:',
        'upload.clergy.req1': 'Ragaa aangoo lubummaa (Diyaakoonummaa/Qeesummaa/Monastikii)',
        'upload.clergy.req2': 'Xalayaa mirkaneessuu mana amantaa',
        'upload.clergy.req3': 'Suuraa ayyaana aangoo lubummaa',
        'upload.clergy.req4': 'Bifa: JPG, PNG, PDF',
        'upload.clergy.req5': 'Guddina guddaa: 5MB',
        
        'upload.photo.title': 'Suuraa Keessan Ol Kaa\'aa',
        'upload.photo.subtitle': 'Barbaachisaa dha, hanga 5MB',
        'upload.photo.prompt': 'Ol Kaa\'uuf Cuqaasi ykn Harkisii Kaa\'i',
        'upload.photo.description': 'Suuraa ifa ta\'e mataa keessanii ol kaa\'aa',
        'upload.photo.button': 'Suuraa Filadhu',
        'upload.photo.requirements': 'Ulaagaalee Suuraa:',
        'upload.photo.req1': 'Suuraa ifa ta\'e, yeroo dhiyoo (akkaataa paaspoortii filatamaa)',
        'upload.photo.req2': 'Fuulli ifa ta\'ee mul\'atu, kaameraa ilaalu',
        'upload.photo.req3': 'Ifa gaarii, duubee salphaa',
        'upload.photo.req4': 'Bifa: JPG, PNG, GIF',
        'upload.photo.req5': 'Guddina guddaa: 5MB',
        'upload.photo.req6': 'Qulqullina gad aanaa: piikselii 300x300',
        
        // Preview
        'preview.document': 'Ilaalcha Ragaa',
        'preview.photo': 'Ilaalcha Suuraa',
        'preview.remove': 'Haqi',
        'preview.filename': 'Maqaa Faayilii:',
        'preview.filesize': 'Guddina Faayilii:',
        'preview.filetype': 'Gosa Faayilii:',
        'preview.dimensions': 'Safara:',
        'preview.status': 'Haala:',
        'preview.ready': 'Qophaa\'e',
        'preview.pdf': 'Galmee PDF',
        'preview.image': 'Suuraa',
        
        // Buttons
        'button.submit': 'Galmaa\'ii Makamuu',
        'button.submitting': 'Akkaawuntii Uumaa jira...',
        
        // Messages
        'message.required': 'Barbaachisaa',
        'message.optional': 'Dirqama Miti',
        'message.security': 'Odeeffannoon keessan icciitiin eegamee nageenya qaba',
        'message.already.account': 'Akkaawuntii qabduu?',
        'message.signin': 'Asitti seenaa',
        
        // Errors
        'error.file.size': 'Guddina faayilii 5MB hin caalu',
        'error.file.type.image': 'Faayilii suuraa ol kaa\'aa (JPG, PNG, GIF)',
        'error.file.type.doc': 'Suuraa (JPG, PNG) ykn faayilii PDF ol kaa\'aa',
        'error.resolution.low': 'Qulqullina suuraa gad aanaa dha. Piikselii 300x300 gad aanaa gorsa.',
        
        // Formats
        'format.accepted': 'Bifoonni fudhataman:',
        'format.jpg.png.gif': 'JPG, PNG, GIF (hanga 5MB)',
        'format.jpg.png.pdf': 'JPG, PNG, PDF (hanga 5MB)',
        
        // Dashboard Common
        'dashboard.welcome': 'Baga Nagaan Dhuftan',
        'dashboard.member': 'Daashboordii Miseensaa',
        'dashboard.admin': 'Daashboordii Bulchaa',
        'dashboard.superadmin': 'Daashboordii Bulchaa Olaanaa',
        'dashboard.logout': 'Ba\'i',
        'dashboard.profile': 'Profaayilii',
        'dashboard.settings': 'Qindaa\'ina',
        'dashboard.home': 'Mana',
        'dashboard.members': 'Miseensota',
        'dashboard.announcements': 'Beeksisalee',
        'dashboard.contributions': 'Gumaacha',
        'dashboard.reports': 'Gabaasalee',
        'dashboard.centers': 'Wiirtuu',
        'dashboard.users': 'Fayyadamtoota',
        'dashboard.analytics': 'Xiinxala',
        
        // Member Dashboard
        'member.myprofile': 'Profaayilii Koo',
        'member.mycontributions': 'Gumaacha Koo',
        'member.announcements': 'Beeksisalee',
        'member.resources': 'Qabeenya',
        'member.memberid': 'Eenyummaa Miseensaa',
        'member.status': 'Haala',
        'member.active': 'Sochii Keessa',
        'member.inactive': 'Sochii Ala',
        'member.pending': 'Eegaa Jiru',
        'member.photo': 'Suuraa Miseensaa',
        'member.changephoto': 'Suuraa Jijjiiri',
        'member.uploadphoto': 'Suuraa Ol Kaa\'i',
        
        // Admin Dashboard
        'admin.managemembers': 'Miseensota Bulchi',
        'admin.managecenters': 'Wiirtuu Bulchi',
        'admin.viewreports': 'Gabaasalee Ilaali',
        'admin.createannouncement': 'Beeksisa Uumi',
        'admin.totalmembers': 'Miseensota Waliigalaa',
        'admin.activecenters': 'Wiirtuu Sochii Keessa',
        'admin.pendingapprovals': 'Raggeessii Eegaa Jiru',
        'admin.recentactivity': 'Sochii Dhiyoo',
        
        // Language Switcher
        'lang.english': 'Ingiliziffaa',
        'lang.oromoo': 'Afaan Oromoo',
        'lang.amharic': 'Amariffaa',
        'lang.tigrinya': 'Tigiriffaa',
        'lang.select': 'Afaan Filadhu',
        
        // AI Assistant
        'assistant.title': 'GARGAARAA TOORA INTERNEETII WDB',
        'assistant.subtitle': '24/7 • Yeroo hunda gargaaruuf qophaa\'e',
        'assistant.input.placeholder': 'Waa\'ee WDB waan kamiyyuu na gaafadha...'
    },
    
    am: {
        // Page Title
        'page.title': 'የWDB ምዝገባ መግቢያ',
        'page.subtitle': 'ወልዳ ዱካ ቡኦታ • የኢትዮጵያ ኦርቶዶክስ ተዋህዶ ቤተክርስቲያን',
        'register.member': 'አዲስ አባል ይመዝገቡ',
        
        // Navigation
        'nav.home': 'መነሻ',
        'nav.login': 'ግባ',
        'nav.register': 'ይመዝገቡ',
        'nav.about': 'ስለ እኛ',
        
        // Form Sections
        'section.personal': 'የግል መረጃ',
        'section.clergy': 'የቀሳውስት ሁኔታ',
        'section.clergy.subtitle': 'የተሾሙ ቀሳውስት ከሆኑ፣ መረጃዎን እና የማረጋገጫ ሰነዶችን ያቅርቡ',
        'section.clergy.optional': 'አማራጭ',
        'section.photo': 'የአባልነት ፎቶ',
        'section.photo.subtitle': 'ለአባልነት መታወቂያ ካርድዎ ግልጽ፣ የቅርብ ጊዜ ፎቶ ይስቀሉ',
        
        // Form Fields
        'field.fullname': 'ሙሉ ስም',
        'field.fullname.placeholder': 'ሙሉ ስምዎ (የመጀመሪያ፣ የአባት፣ የአያት)',
        'field.email': 'የኢሜይል አድራሻ',
        'field.email.placeholder': 'ለምሳሌ ስም@ምሳሌ.com',
        'field.username': 'የተጠቃሚ ስም ይምረጡ',
        'field.username.placeholder': 'የተጠቃሚ ስም (የመጀመሪያዎቹ 8 ፊደላት)',
        'field.password': 'የይለፍ ቃል',
        'field.password.placeholder': 'የይለፍ ቃልዎ',
        'field.phone': 'የሞባይል ስልክ',
        'field.phone.placeholder': '+251 XXX XXX XXX',
        'field.gender': 'ጾታ',
        'field.gender.select': 'ጾታ ይምረጡ',
        'field.gender.male': 'ወንድ',
        'field.gender.female': 'ሴት',
        'field.gender.other': 'ሌላ',
        'field.membership.date': 'የዛሬ ቀን የአባልነት መጀመሪያ ቀን',
        
        // Clergy Fields
        'field.diaconate.year': 'የዲያቆንነት ዓመት',
        'field.diaconate.year.placeholder': 'ለምሳሌ 2015',
        'field.diaconate.church': 'የዲያቆንነት ቤተክርስቲያን',
        'field.diaconate.church.placeholder': 'የቤተክርስቲያን ስም',
        'field.priesthood.year': 'የቀሳውስትነት ዓመት',
        'field.priesthood.year.placeholder': 'ለምሳሌ 2018',
        'field.priesthood.church': 'የቀሳውስትነት ቤተክርስቲያን',
        'field.priesthood.church.placeholder': 'የቤተክርስቲያን ስም',
        'field.monastic.year': 'የመነኮስነት ዓመት',
        'field.monastic.year.placeholder': 'ለምሳሌ 2020',
        'field.monastic.kawaala': 'የመነኮስነት ቃዋላ',
        'field.monastic.kawaala.placeholder': 'የቃዋላ ስም',
        
        // File Upload
        'upload.clergy.title': 'የቀሳውስት ሰነዶች (ፎቶ/PDF)',
        'upload.clergy.subtitle': 'አማራጭ፣ ከ5MB በታች',
        'upload.clergy.prompt': 'ለመስቀል ይጫኑ ወይም ይጎትቱ',
        'upload.clergy.description': 'የሹመት የምስክር ወረቀት ወይም የማረጋገጫ ሰነዶችን ይስቀሉ',
        'upload.clergy.button': 'ሰነድ ይምረጡ',
        'upload.clergy.requirements': 'የሚቀበሉ ሰነዶች:',
        'upload.clergy.req1': 'የሹመት የምስክር ወረቀት (ዲያቆንነት/ቀሳውስትነት/መነኮስነት)',
        'upload.clergy.req2': 'የቤተክርስቲያን የማረጋገጫ ደብዳቤ',
        'upload.clergy.req3': 'የሹመት ሥነ ሥርዓት ፎቶ',
        'upload.clergy.req4': 'ቅርጸቶች: JPG, PNG, PDF',
        'upload.clergy.req5': 'ከፍተኛ መጠን: 5MB',
        
        'upload.photo.title': 'ፎቶዎን ይስቀሉ',
        'upload.photo.subtitle': 'ያስፈልጋል፣ ከ5MB በታች',
        'upload.photo.prompt': 'ለመስቀል ይጫኑ ወይም ይጎትቱ',
        'upload.photo.description': 'ግልጽ የሆነ የራስዎን ፎቶ ይስቀሉ',
        'upload.photo.button': 'ፎቶ ይምረጡ',
        'upload.photo.requirements': 'የፎቶ መስፈርቶች:',
        'upload.photo.req1': 'ግልጽ፣ የቅርብ ጊዜ ፎቶ (የፓስፖርት ዘይቤ ይመረጣል)',
        'upload.photo.req2': 'ፊት በግልጽ የሚታይ፣ ካሜራውን እያየ',
        'upload.photo.req3': 'ጥሩ ብርሃን፣ ቀላል ዳራ',
        'upload.photo.req4': 'ቅርጸቶች: JPG, PNG, GIF',
        'upload.photo.req5': 'ከፍተኛ መጠን: 5MB',
        'upload.photo.req6': 'ዝቅተኛ ጥራት: 300x300 ፒክሰል',
        
        // Preview
        'preview.document': 'የሰነድ ቅድመ እይታ',
        'preview.photo': 'የፎቶ ቅድመ እይታ',
        'preview.remove': 'አስወግድ',
        'preview.filename': 'የፋይል ስም:',
        'preview.filesize': 'የፋይል መጠን:',
        'preview.filetype': 'የፋይል አይነት:',
        'preview.dimensions': 'መጠኖች:',
        'preview.status': 'ሁኔታ:',
        'preview.ready': 'ዝግጁ',
        'preview.pdf': 'PDF ሰነድ',
        'preview.image': 'ምስል',
        
        // Buttons
        'button.submit': 'ይመዝገቡ እና ይቀላቀሉ',
        'button.submitting': 'መለያ በመፍጠር ላይ...',
        
        // Messages
        'message.required': 'ያስፈልጋል',
        'message.optional': 'አማራጭ',
        'message.security': 'መረጃዎ የተመሰጠረ እና ደህንነቱ የተጠበቀ ነው',
        'message.already.account': 'መለያ አለዎት?',
        'message.signin': 'እዚህ ይግቡ',
        
        // Errors
        'error.file.size': 'የፋይል መጠን ከ5MB መብለጥ የለበትም',
        'error.file.type.image': 'እባክዎ የምስል ፋይል ይስቀሉ (JPG, PNG, GIF)',
        'error.file.type.doc': 'እባክዎ ምስል (JPG, PNG) ወይም PDF ፋይል ይስቀሉ',
        'error.resolution.low': 'የፎቶ ጥራት ዝቅተኛ ነው። ዝቅተኛው 300x300 ፒክሰል ይመከራል።',
        
        // Formats
        'format.accepted': 'የሚቀበሉ ቅርጸቶች:',
        'format.jpg.png.gif': 'JPG, PNG, GIF (ከ5MB በታች)',
        'format.jpg.png.pdf': 'JPG, PNG, PDF (ከ5MB በታች)',
        
        // Dashboard Common
        'dashboard.welcome': 'እንኳን ደህና መጡ',
        'dashboard.member': 'የአባል ዳሽቦርድ',
        'dashboard.admin': 'የአስተዳዳሪ ዳሽቦርድ',
        'dashboard.superadmin': 'የከፍተኛ አስተዳዳሪ ዳሽቦርድ',
        'dashboard.logout': 'ውጣ',
        'dashboard.profile': 'መገለጫ',
        'dashboard.settings': 'ቅንብሮች',
        'dashboard.home': 'መነሻ',
        'dashboard.members': 'አባላት',
        'dashboard.announcements': 'ማስታወቂያዎች',
        'dashboard.contributions': 'አስተዋጽዖዎች',
        'dashboard.reports': 'ሪፖርቶች',
        'dashboard.centers': 'ማዕከላት',
        'dashboard.users': 'ተጠቃሚዎች',
        'dashboard.analytics': 'ትንታኔዎች',
        
        // Member Dashboard
        'member.myprofile': 'የእኔ መገለጫ',
        'member.mycontributions': 'የእኔ አስተዋጽዖዎች',
        'member.announcements': 'ማስታወቂያዎች',
        'member.resources': 'ግብዓቶች',
        'member.memberid': 'የአባል መታወቂያ',
        'member.status': 'ሁኔታ',
        'member.active': 'ንቁ',
        'member.inactive': 'ንቁ ያልሆነ',
        'member.pending': 'በመጠባበቅ ላይ',
        'member.photo': 'የአባል ፎቶ',
        'member.changephoto': 'ፎቶ ቀይር',
        'member.uploadphoto': 'ፎቶ ስቀል',
        
        // Admin Dashboard
        'admin.managemembers': 'አባላትን አስተዳድር',
        'admin.managecenters': 'ማዕከላትን አስተዳድር',
        'admin.viewreports': 'ሪፖርቶችን ይመልከቱ',
        'admin.createannouncement': 'ማስታወቂያ ይፍጠሩ',
        'admin.totalmembers': 'ጠቅላላ አባላት',
        'admin.activecenters': 'ንቁ ማዕከላት',
        'admin.pendingapprovals': 'በመጠባበቅ ላይ ያሉ ፈቃዶች',
        'admin.recentactivity': 'የቅርብ ጊዜ እንቅስቃሴ',
        
        // Language Switcher
        'lang.english': 'እንግሊዝኛ',
        'lang.oromoo': 'አፋን ኦሮሞ',
        'lang.amharic': 'አማርኛ',
        'lang.tigrinya': 'ትግርኛ',
        'lang.select': 'ቋንቋ ይምረጡ',
        
        // AI Assistant
        'assistant.title': 'የWDB የመስመር ላይ ረዳት',
        'assistant.subtitle': '24/7 • ሁልጊዜ ለመርዳት እዚህ ነን',
        'assistant.input.placeholder': 'ስለ WDB ማንኛውንም ነገር ይጠይቁኝ...'
    },
    
    ti: {
        // Page Title
        'page.title': 'WDB መመዝገቢ መድረኽ',
        'page.subtitle': 'ወልዳ ዱካ ቡኦታ • ናይ ኢትዮጵያ ኦርቶዶክስ ተዋህዶ ቤተክርስትያን',
        'register.member': 'ሓድሽ ኣባል ምዝገባ',
        
        // Navigation
        'nav.home': 'ገጽ ቤት',
        'nav.login': 'ኣትው',
        'nav.register': 'ምዝገባ',
        'nav.about': 'ብዛዕባና',
        
        // Form Sections
        'section.personal': 'ውልቃዊ ሓበሬታ',
        'section.clergy': 'ናይ ቀሳውስቲ ኩነታት',
        'section.clergy.subtitle': 'ተሾሙ ቀሳውስቲ እንተኾይንኩም፣ ሓበሬታኹምን ናይ ምርግጋጽ ሰነዳትኩምን ኣቕርቡ',
        'section.clergy.optional': 'ኣማራጺ',
        'section.photo': 'ናይ ኣባልነት ስእሊ',
        'section.photo.subtitle': 'ንናይ ኣባልነት መለለዪ ካርድኩም ንጹር፣ ናይ ቀረባ እዋን ስእሊ ኣስቀልዑ',
        
        // Form Fields
        'field.fullname': 'ምሉእ ስም',
        'field.fullname.placeholder': 'ምሉእ ስምኩም (ቀዳማይ፣ ማእከላይ፣ መወዳእታ)',
        'field.email': 'ናይ ኢመይል ኣድራሻ',
        'field.email.placeholder': 'ንኣብነት ስም@ኣብነት.com',
        'field.username': 'ናይ ተጠቃሚ ስም ምረጹ',
        'field.username.placeholder': 'ናይ ተጠቃሚ ስም (ቀዳሞት 8 ፊደላት)',
        'field.password': 'ናይ ምሕላፍ ቃል',
        'field.password.placeholder': 'ናይ ምሕላፍ ቃልኩም',
        'field.phone': 'ናይ ሞባይል ተሌፎን',
        'field.phone.placeholder': '+251 XXX XXX XXX',
        'field.gender': 'ጾታ',
        'field.gender.select': 'ጾታ ምረጹ',
        'field.gender.male': 'ተባዕታይ',
        'field.gender.female': 'ኣንስተይቲ',
        'field.gender.other': 'ካልእ',
        'field.membership.date': 'ናይ ሎሚ ዕለት ናይ ኣባልነት ምጅማር ዕለት',
        
        // Clergy Fields
        'field.diaconate.year': 'ናይ ዲያቆንነት ዓመት',
        'field.diaconate.year.placeholder': 'ንኣብነት 2015',
        'field.diaconate.church': 'ናይ ዲያቆንነት ቤተክርስትያን',
        'field.diaconate.church.placeholder': 'ስም ቤተክርስትያን',
        'field.priesthood.year': 'ናይ ቀሳውስትነት ዓመት',
        'field.priesthood.year.placeholder': 'ንኣብነት 2018',
        'field.priesthood.church': 'ናይ ቀሳውስትነት ቤተክርስትያን',
        'field.priesthood.church.placeholder': 'ስም ቤተክርስትያን',
        'field.monastic.year': 'ናይ መነኮስነት ዓመት',
        'field.monastic.year.placeholder': 'ንኣብነት 2020',
        'field.monastic.kawaala': 'ናይ መነኮስነት ቃዋላ',
        'field.monastic.kawaala.placeholder': 'ስም ቃዋላ',
        
        // File Upload
        'upload.clergy.title': 'ናይ ቀሳውስቲ ሰነዳት (ስእሊ/PDF)',
        'upload.clergy.subtitle': 'ኣማራጺ፣ ክሳብ 5MB',
        'upload.clergy.prompt': 'ንምስቃል ጠውቑ ወይ ስሕቡ',
        'upload.clergy.description': 'ናይ ሹመት ምስክር ወረቐት ወይ ናይ ምርግጋጽ ሰነዳት ኣስቀልዑ',
        'upload.clergy.button': 'ሰነድ ምረጹ',
        'upload.clergy.requirements': 'ዝቕበሉ ሰነዳት:',
        'upload.clergy.req1': 'ናይ ሹመት ምስክር ወረቐት (ዲያቆንነት/ቀሳውስትነት/መነኮስነት)',
        'upload.clergy.req2': 'ናይ ቤተክርስትያን ናይ ምርግጋጽ ደብዳቤ',
        'upload.clergy.req3': 'ናይ ሹመት ስነ ስርዓት ስእሊ',
        'upload.clergy.req4': 'ቅርጺታት: JPG, PNG, PDF',
        'upload.clergy.req5': 'ዝለዓለ መጠን: 5MB',
        
        'upload.photo.title': 'ስእልኹም ኣስቀልዑ',
        'upload.photo.subtitle': 'የድሊ፣ ክሳብ 5MB',
        'upload.photo.prompt': 'ንምስቃል ጠውቑ ወይ ስሕቡ',
        'upload.photo.description': 'ንጹር ናይ ርእስኹም ስእሊ ኣስቀልዑ',
        'upload.photo.button': 'ስእሊ ምረጹ',
        'upload.photo.requirements': 'ናይ ስእሊ መስፈርታት:',
        'upload.photo.req1': 'ንጹር፣ ናይ ቀረባ እዋን ስእሊ (ናይ ፓስፖርት ቅዲ ይመረጽ)',
        'upload.photo.req2': 'ገጽ ብንጹር ዝረአ፣ ንካሜራ እናጠመተ',
        'upload.photo.req3': 'ጽቡቕ ብርሃን፣ ቀሊል ድሕረ ባይታ',
        'upload.photo.req4': 'ቅርጺታት: JPG, PNG, GIF',
        'upload.photo.req5': 'ዝለዓለ መጠን: 5MB',
        'upload.photo.req6': 'ዝተሓተ ጽሬት: 300x300 ፒክሰል',
        
        // Preview
        'preview.document': 'ናይ ሰነድ ቅድመ ምርኣይ',
        'preview.photo': 'ናይ ስእሊ ቅድመ ምርኣይ',
        'preview.remove': 'ኣልግስ',
        'preview.filename': 'ስም ፋይል:',
        'preview.filesize': 'መጠን ፋይል:',
        'preview.filetype': 'ዓይነት ፋይል:',
        'preview.dimensions': 'መጠናት:',
        'preview.status': 'ኩነታት:',
        'preview.ready': 'ድሉው',
        'preview.pdf': 'PDF ሰነድ',
        'preview.image': 'ምስሊ',
        
        // Buttons
        'button.submit': 'ምዝገባን ምውህሃድን',
        'button.submitting': 'መለለዪ ኣብ ምፍጣር...',
        
        // Messages
        'message.required': 'የድሊ',
        'message.optional': 'ኣማራጺ',
        'message.security': 'ሓበሬታኹም ዝተመስጠረን ውሕስነቱ ዝተሓለወን እዩ',
        'message.already.account': 'መለለዪ ኣለኩም?',
        'message.signin': 'ኣብዚ ኣትዉ',
        
        // Errors
        'error.file.size': 'መጠን ፋይል ካብ 5MB ክሓልፍ የብሉን',
        'error.file.type.image': 'በጃኹም ናይ ምስሊ ፋይል ኣስቀልዑ (JPG, PNG, GIF)',
        'error.file.type.doc': 'በጃኹም ምስሊ (JPG, PNG) ወይ PDF ፋይል ኣስቀልዑ',
        'error.resolution.low': 'ናይ ስእሊ ጽሬት ትሑት እዩ። ዝተሓተ 300x300 ፒክሰል ይመከር።',
        
        // Formats
        'format.accepted': 'ዝቕበሉ ቅርጺታት:',
        'format.jpg.png.gif': 'JPG, PNG, GIF (ክሳብ 5MB)',
        'format.jpg.png.pdf': 'JPG, PNG, PDF (ክሳብ 5MB)',
        
        // Dashboard Common
        'dashboard.welcome': 'እንቋዕ ብደሓን መጻእኩም',
        'dashboard.member': 'ናይ ኣባል ዳሽቦርድ',
        'dashboard.admin': 'ናይ ኣስተዳዳሪ ዳሽቦርድ',
        'dashboard.superadmin': 'ናይ ልዑል ኣስተዳዳሪ ዳሽቦርድ',
        'dashboard.logout': 'ውጻእ',
        'dashboard.profile': 'መገለጺ',
        'dashboard.settings': 'ምቕናዋት',
        'dashboard.home': 'ገጽ ቤት',
        'dashboard.members': 'ኣባላት',
        'dashboard.announcements': 'መግለጺታት',
        'dashboard.contributions': 'ኣበርክቶታት',
        'dashboard.reports': 'ጸብጻባት',
        'dashboard.centers': 'ማእከላት',
        'dashboard.users': 'ተጠቀምቲ',
        'dashboard.analytics': 'ትንተናታት',
        
        // Member Dashboard
        'member.myprofile': 'መገለጺይ',
        'member.mycontributions': 'ኣበርክቶታተይ',
        'member.announcements': 'መግለጺታት',
        'member.resources': 'ጸጋታት',
        'member.memberid': 'መለለዪ ኣባል',
        'member.status': 'ኩነታት',
        'member.active': 'ንጡፍ',
        'member.inactive': 'ንጡፍ ዘይኮነ',
        'member.pending': 'ኣብ ምጽባይ',
        'member.photo': 'ናይ ኣባል ስእሊ',
        'member.changephoto': 'ስእሊ ቀይር',
        'member.uploadphoto': 'ስእሊ ኣስቀልዕ',
        
        // Admin Dashboard
        'admin.managemembers': 'ኣባላት ኣስተዳድር',
        'admin.managecenters': 'ማእከላት ኣስተዳድር',
        'admin.viewreports': 'ጸብጻባት ርአ',
        'admin.createannouncement': 'መግለጺ ፍጠር',
        'admin.totalmembers': 'ጠቅላላ ኣባላት',
        'admin.activecenters': 'ንጡፋት ማእከላት',
        'admin.pendingapprovals': 'ኣብ ምጽባይ ዘለዉ ፍቓዳት',
        'admin.recentactivity': 'ናይ ቀረባ እዋን ንጥፈታት',
        
        // Language Switcher
        'lang.english': 'እንግሊዝኛ',
        'lang.oromoo': 'ኣፋን ኦሮሞ',
        'lang.amharic': 'ኣምሓርኛ',
        'lang.tigrinya': 'ትግርኛ',
        'lang.select': 'ቋንቋ ምረጹ',
        
        // AI Assistant
        'assistant.title': 'WDB ናይ ኢንተርነት ሓጋዚ',
        'assistant.subtitle': '24/7 • ኩሉ ግዜ ንምሕጋዝ ኣብዚ ኣለና',
        'assistant.input.placeholder': 'ብዛዕባ WDB ዝኾነ ነገር ሕተቱኒ...'
    }
};

// Translation Manager
class TranslationManager {
    constructor() {
        this.currentLang = localStorage.getItem('wdb_language') || 'en';
        this.translations = translations;
    }
    
    setLanguage(lang) {
        if (this.translations[lang]) {
            this.currentLang = lang;
            localStorage.setItem('wdb_language', lang);
            this.translatePage();
        }
    }
    
    translate(key) {
        return this.translations[this.currentLang][key] || this.translations['en'][key] || key;
    }
    
    translatePage() {
        // Translate all elements with data-i18n attribute
        document.querySelectorAll('[data-i18n]').forEach(element => {
            const key = element.getAttribute('data-i18n');
            const translation = this.translate(key);
            
            if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
                if (element.placeholder) {
                    element.placeholder = translation;
                }
            } else {
                element.textContent = translation;
            }
        });
        
        // Translate placeholders
        document.querySelectorAll('[data-i18n-placeholder]').forEach(element => {
            const key = element.getAttribute('data-i18n-placeholder');
            element.placeholder = this.translate(key);
        });
        
        // Translate titles
        document.querySelectorAll('[data-i18n-title]').forEach(element => {
            const key = element.getAttribute('data-i18n-title');
            element.title = this.translate(key);
        });
        
        // Update page title
        document.title = this.translate('page.title');
    }
    
    getCurrentLanguage() {
        return this.currentLang;
    }
}

// Initialize translation manager
const i18n = new TranslationManager();

// Auto-translate on page load
document.addEventListener('DOMContentLoaded', () => {
    i18n.translatePage();
});
