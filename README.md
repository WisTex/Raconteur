**ZAP**

Zap is a social networking app running under the Zot/6 protocol and the LAMP web stack.

Protocol documentation is located here:

https://macgirvin.com/wiki/mike/Zot%2BVI/Home

Zap is based on Red, which in turn is based on Hubzilla. It is otherwise unrelated to those projects and the software has a completely different scope and purpose. 


01-August-2018
==============

Most of the basic functionality is now present and working. This is still "use at your own risk", but it shouldn't burn down the house. 


19-July-2018
============

There is a lot more work yet to be done, but the basic Zap application is nearing alpha quality.

TODO before alpha release:

* convert mail to ActivityStreams
* test nomadic channels
* correct any links in the documentation and remove the descriptions of Hubzilla features which do not exist in Zap






**Things you should know**

Zap is nomadic and does not federate with any other platform or protocol currently. It will only **ever** federate with nomadic-aware services/protocols. Full stop. Full federation support will eventually be provided by creating bridging identities in a companion project Osada; which provides a bridge between nomadic and non-nomadic networks. Osada identities cannot be nomadic since they federate with non-nomadic services, but they can be linked to Zap nomadic identities using Zot6 identity linking. 

If you are looking for a specific Hubzilla feature, you came to the wrong place.

If you are looking for ActivityPub support, you came to the wrong place. 

If you are looking for stable software, check back in a few months.

If you encounter issues, fix them and submit a pull request.

Pull requests which add unnecessary features will be ignored. These should be implemented using apps and/or addons.

The database configuration is not yet "stable". You probably do not want to install this as a production service at this time, and please do not use this software for storing any critical information which is not backed up elsewhere.   




  





